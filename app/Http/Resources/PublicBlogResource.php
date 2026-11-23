<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Published blog for the website (no admin-only fields).
 *
 * @mixin \App\Models\Blog
 */
class PublicBlogResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt ?? '',
            'content' => $this->when($this->withContent, fn () => $this->content ?? ''),
            'image' => $this->imageUrl(),
            'imageAlt' => $this->image_alt ?: $this->title,
            'category' => $this->category ? ['name' => $this->category->name, 'slug' => $this->category->slug] : null,
            'author' => $this->author ? ['name' => $this->author->name, 'bio' => $this->author->bio ?? '', 'image' => $this->author->imageUrl()] : null,
            'tags' => $this->tags ?? [],
            'seoTitle' => $this->when($this->withContent, fn () => $this->seo_title ?: $this->title),
            'metaDescription' => $this->when($this->withContent, fn () => $this->meta_description ?: ($this->excerpt ?? '')),
            'readingMinutes' => $this->reading_minutes,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
