<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PublicBlogIndexRequest;
use App\Http\Resources\PublicBlogResource;
use App\Models\Blog;
use App\Support\PublicCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Read-only endpoints for the public website (published posts only). */
class BlogController extends Controller
{
    private const LIST_COLUMNS = [
        'id', 'title', 'slug', 'excerpt', 'image_path', 'image_alt', 'category_id', 'author_id',
        'tags', 'reading_minutes', 'published_at', 'updated_at',
    ];

    public function index(PublicBlogIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $key = 'list:'.md5(json_encode($filters + ['page' => (int) $request->query('page', 1)]));

        $payload = PublicCache::remember($key, function () use ($filters) {
            $page = Blog::query()
                ->published()
                ->select(self::LIST_COLUMNS)
                ->with(['category:id,name,slug', 'author:id,name,bio,image_path'])
                ->when($filters['category'] ?? null, fn (Builder $q, $slug) => $q->whereHas('category', fn (Builder $c) => $c->where('slug', $slug)))
                ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                    $like = '%'.addcslashes($search, '%_\\').'%';
                    $q->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like));
                })
                ->latest('published_at')
                ->orderByDesc('id')
                ->paginate($filters['perPage'] ?? 9);

            return PublicBlogResource::collection($page)->response()->getData(true);
        });

        return response()->json($payload)->header('Cache-Control', 'public, max-age=60');
    }

    public function show(string $slug): JsonResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,140}$/', $slug), 404);

        $payload = PublicCache::remember("post:{$slug}", function () use ($slug) {
            $blog = Blog::query()->published()->where('slug', $slug)->with(['category', 'author'])->first();
            if (! $blog) {
                return null;
            }

            $related = Blog::query()
                ->published()
                ->select(self::LIST_COLUMNS)
                ->with(['category:id,name,slug', 'author:id,name,bio,image_path'])
                ->whereKeyNot($blog->id)
                ->when($blog->category_id, fn (Builder $q) => $q->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$blog->category_id]))
                ->latest('published_at')
                ->limit(3)
                ->get();

            return [
                'data' => PublicBlogResource::make($blog)->withContent()->resolve(),
                'related' => PublicBlogResource::collection($related)->resolve(),
            ];
        });

        abort_if($payload === null, 404, 'Post not found.');

        return response()->json($payload)->header('Cache-Control', 'public, max-age=60');
    }

    /** Counts a page view (rate-limited per visitor). */
    public function view(string $slug): JsonResponse
    {
        // Direct query: does not touch updated_at or clear the cache.
        $updated = DB::table('blogs')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->increment('views');

        abort_if($updated === 0, 404, 'Post not found.');

        return response()->json(['ok' => true]);
    }
}
