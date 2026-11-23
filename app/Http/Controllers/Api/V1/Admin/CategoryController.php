<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\PublicCache;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->withCount('blogs')->orderBy('name')->get());
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $name = $request->validated('name');
        $category = Category::create(['name' => $name, 'slug' => Slug::unique(Category::class, $name, null, 60)]);

        return CategoryResource::make($category->loadCount('blogs'))->response()->setStatusCode(201);
    }

    /** Renaming also updates the slug; blogs keep their link by id. */
    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $name = $request->validated('name');
        $category->update(['name' => $name, 'slug' => Slug::unique(Category::class, $name, $category->id, 60)]);
        PublicCache::flush();

        return CategoryResource::make($category->loadCount('blogs'));
    }

    /** Blogs in this category become uncategorised (category_id = null). */
    public function destroy(Category $category): JsonResponse
    {
        $affected = $category->blogs()->count();
        $category->delete();
        PublicCache::flush();

        return response()->json(['message' => "Category \"{$category->name}\" deleted.", 'affectedBlogs' => $affected]);
    }
}
