<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaUpdateRequest;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Http\Resources\MediaItemResource;
use App\Models\MediaItem;
use App\Support\ImageOptimizer;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/** Media library: images used inside blog content. */
class MediaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $items = MediaItem::query()
            ->with('user:id,name')
            ->when($data['search'] ?? null, function (Builder $q, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('alt', 'like', $like));
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate($data['perPage'] ?? 24)
            ->withQueryString();

        return MediaItemResource::collection($items);
    }

    public function store(MediaUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $stored = ImageOptimizer::store($file, 'content');

        try {
            $item = MediaItem::create($stored + [
                'name' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'image', 140, ''),
                'alt' => $request->validated('alt'),
                'user_id' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            Media::delete($stored['path']);
            throw $e;
        }

        return MediaItemResource::make($item->load('user:id,name'))->response()->setStatusCode(201);
    }

    public function update(MediaUpdateRequest $request, MediaItem $media): MediaItemResource
    {
        $media->update(['alt' => $request->validated('alt')]);

        return MediaItemResource::make($media->load('user:id,name'));
    }

    /** Images still used in a post need ?force=1 (the post would show a broken image). */
    public function destroy(Request $request, MediaItem $media): JsonResponse
    {
        $usedIn = $media->usedIn();

        if ($usedIn->isNotEmpty() && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'This image is used in '.$usedIn->count().' '.Str::plural('post', $usedIn->count()).'. Remove it from the post first, or delete it anyway.',
                'usedIn' => $usedIn->map(fn ($blog) => ['id' => $blog->id, 'title' => $blog->title])->values(),
            ], 409);
        }

        $media->delete();
        Media::delete($media->path);

        return response()->json(['message' => 'Image deleted.']);
    }
}
