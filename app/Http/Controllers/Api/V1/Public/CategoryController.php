<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\PublicCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /** Categories that have at least one published post, with counts. */
    public function index(): JsonResponse
    {
        $payload = PublicCache::remember('categories', fn () => Category::query()
            ->whereHas('blogs', fn (Builder $q) => $q->published())
            ->withCount(['blogs' => fn (Builder $q) => $q->published()])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug, 'blogsCount' => $c->blogs_count])
            ->all());

        return response()->json(['data' => $payload])->header('Cache-Control', 'public, max-age=60');
    }
}
