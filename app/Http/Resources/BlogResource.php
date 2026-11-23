<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Blog for the admin panel. Lists leave out `content`; pass
 * `BlogResource::make($blog)->withContent()` for the editor.
 *
 * @mixin \App\Models\Blog
 */
class BlogResource extends JsonResource
{
    private bool $withContent = false;

    public function withContent(): static
    {
        $this->withContent = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt ?? '',
            'content' => $this->when($this->withContent, fn () => $this->content ?? ''),
            'image' => $this->imageUrl(),
            'imageAlt' => $this->image_alt ?? '',
            'categoryId' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null),
            'authorId' => $this->author_id,
            'author' => $this->whenLoaded('author', fn () => $this->author ? ['id' => $this->author->id, 'name' => $this->author->name, 'image' => $this->author->imageUrl()] : null),
            'tags' => $this->tags ?? [],
            'seoTitle' => $this->seo_title ?? '',
            'metaDescription' => $this->meta_description ?? '',
            'status' => $this->status->value,
            'views' => $this->views,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
