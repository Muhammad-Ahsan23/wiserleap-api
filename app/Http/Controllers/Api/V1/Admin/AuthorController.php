<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use App\Support\Media;
use App\Support\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuthorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AuthorResource::collection(Author::query()->withCount('blogs')->orderBy('name')->get());
    }

    public function store(AuthorRequest $request): JsonResponse
    {
        $image = $request->file('image') ? Media::store($request->file('image'), 'authors') : null;

        try {
            $author = Author::create([...$request->safe()->only(['name', 'bio']), 'image_path' => $image]);
        } catch (\Throwable $e) {
            Media::delete($image);
            throw $e;
        }

        return AuthorResource::make($author->loadCount('blogs'))->response()->setStatusCode(201);
    }

    public function update(AuthorRequest $request, Author $author): AuthorResource
    {
        $oldImage = $author->image_path;
        $newImage = $request->file('image') ? Media::store($request->file('image'), 'authors') : null;

        $author->fill($request->safe()->only(['name', 'bio']));
        if ($newImage) {
            $author->image_path = $newImage;
        } elseif ($request->boolean('removeImage')) {
            $author->image_path = null;
        }

        try {
            $author->save();
        } catch (\Throwable $e) {
            Media::delete($newImage);
            throw $e;
        }

        if ($oldImage && $oldImage !== $author->image_path) {
            Media::delete($oldImage);
        }
        PublicCache::flush();

        return AuthorResource::make($author->loadCount('blogs'));
    }

    /** Blogs by this author keep existing without an author (author_id = null). */
    public function destroy(Author $author): JsonResponse
    {
        $affected = $author->blogs()->count();
        $author->delete();
        Media::delete($author->image_path);
        PublicCache::flush();

        return response()->json(['message' => "Author \"{$author->name}\" deleted.", 'affectedBlogs' => $affected]);
    }
}
