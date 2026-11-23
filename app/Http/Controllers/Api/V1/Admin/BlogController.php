<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BlogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogIndexRequest;
use App\Http\Requests\Admin\BlogRequest;
use App\Http\Requests\Admin\BlogStatusRequest;
use App\Http\Resources\BlogResource;
use App\Models\Blog;
use App\Services\BlogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlogController extends Controller
{
    public function __construct(private readonly BlogService $blogs) {}

    /** Paginated list with filters; `meta.counts` feeds the status tabs. */
    public function index(BlogIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $base = Blog::query()
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like));
            })
            ->when($filters['categoryId'] ?? null, fn (Builder $q, $id) => $id === 'none' ? $q->whereNull('category_id') : $q->where('category_id', $id))
            ->when($filters['authorId'] ?? null, fn (Builder $q, $id) => $id === 'none' ? $q->whereNull('author_id') : $q->where('author_id', $id));

        $counts = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $blogs = (clone $base)
            ->with(['category:id,name,slug', 'author:id,name,image_path'])
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->tap(fn (Builder $q) => match ($filters['sort'] ?? 'updated') {
                'newest' => $q->latest(),
                'oldest' => $q->oldest(),
                'title' => $q->orderBy('title'),
                'views' => $q->orderByDesc('views'),
                default => $q->latest('updated_at'),
            })
            ->orderByDesc('id')
            ->paginate($filters['perPage'] ?? 8)
            ->withQueryString();

        return BlogResource::collection($blogs)->additional(['meta' => ['counts' => [
            'all' => (int) $counts->sum(),
            'published' => (int) ($counts[BlogStatus::Published->value] ?? 0),
            'draft' => (int) ($counts[BlogStatus::Draft->value] ?? 0),
        ]]]);
    }

    public function store(BlogRequest $request): JsonResponse
    {
        $blog = $this->blogs->create($request->safe()->except(['image', 'removeImage']), $request->file('image'));

        return BlogResource::make($blog)->withContent()->response()->setStatusCode(201);
    }

    public function show(Blog $blog): BlogResource
    {
        return BlogResource::make($blog->load(['category', 'author']))->withContent();
    }

    public function update(BlogRequest $request, Blog $blog): BlogResource
    {
        $blog = $this->blogs->update(
            $blog,
            $request->safe()->except(['image', 'removeImage']),
            $request->file('image'),
            $request->boolean('removeImage'),
        );

        return BlogResource::make($blog)->withContent();
    }

    /** Publish / unpublish from the list. */
    public function status(BlogStatusRequest $request, Blog $blog): BlogResource
    {
        $blog = $this->blogs->setStatus($blog, BlogStatus::from($request->validated('status')));

        return BlogResource::make($blog);
    }

    public function destroy(Blog $blog): JsonResponse
    {
        $this->blogs->delete($blog);

        return response()->json(['message' => "\"{$blog->title}\" was deleted."]);
    }
}
