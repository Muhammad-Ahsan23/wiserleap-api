<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\BlogStatus;
use App\Models\Blog;
use App\Support\Media;
use App\Support\PublicCache;
use App\Support\Slug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * All blog writes go through here: slug, sanitising, image files, publish
 * date, activity feed and cache refresh stay consistent for every endpoint.
 */
class BlogService
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly ActivityLogger $activity,
    ) {}

    /** @param array<string, mixed> $data validated input */
    public function create(array $data, ?UploadedFile $image): Blog
    {
        $newImage = $image ? Media::store($image, 'blogs') : null;

        try {
            $blog = DB::transaction(function () use ($data, $newImage) {
                $blog = new Blog($this->attributes($data));
                $blog->slug = Slug::unique(Blog::class, $data['slug'] ?? $data['title']);
                $blog->image_path = $newImage;
                $blog->published_at = $blog->isPublished() ? now() : null;
                $blog->save();

                $this->activity->log($blog->isPublished() ? ActivityAction::Published : ActivityAction::Created, $blog->title);

                return $blog;
            });
        } catch (\Throwable $e) {
            Media::delete($newImage); // don't leave orphan files behind
            throw $e;
        }

        PublicCache::flush();

        // refresh() picks up database defaults (views = 0, reading time).
        return $blog->refresh()->load(['category', 'author']);
    }

    /** @param array<string, mixed> $data validated input */
    public function update(Blog $blog, array $data, ?UploadedFile $image, bool $removeImage = false): Blog
    {
        $wasPublished = $blog->isPublished();
        $oldImage = $blog->image_path;
        $newImage = $image ? Media::store($image, 'blogs') : null;

        try {
            DB::transaction(function () use ($blog, $data, $newImage, $removeImage, $wasPublished) {
                $blog->fill($this->attributes($data));

                if (array_key_exists('slug', $data) && $data['slug'] !== $blog->getOriginal('slug')) {
                    $blog->slug = Slug::unique(Blog::class, $data['slug'] ?: $blog->title, $blog->id);
                }
                if ($newImage) {
                    $blog->image_path = $newImage;
                } elseif ($removeImage) {
                    $blog->image_path = null;
                }
                // Keep the first publish date when a post is re-published.
                if ($blog->isPublished() && ! $blog->published_at) {
                    $blog->published_at = now();
                }

                $blog->save();

                $this->activity->log($this->actionFor($wasPublished, $blog->isPublished()), $blog->title);
            });
        } catch (\Throwable $e) {
            Media::delete($newImage);
            throw $e;
        }

        // Remove the replaced file only after the database change succeeded.
        if ($oldImage && $oldImage !== $blog->image_path) {
            Media::delete($oldImage);
        }

        PublicCache::flush();

        return $blog->load(['category', 'author']);
    }

    public function setStatus(Blog $blog, BlogStatus $status): Blog
    {
        return $this->update($blog, ['status' => $status->value], null);
    }

    public function delete(Blog $blog): void
    {
        $image = $blog->image_path;
        $title = $blog->title;

        DB::transaction(function () use ($blog, $title) {
            $blog->delete();
            $this->activity->log(ActivityAction::Deleted, $title);
        });

        Media::delete($image);
        PublicCache::flush();
    }

    /** Maps the API fields (camelCase) to database columns. */
    private function attributes(array $data): array
    {
        $map = [
            'title' => 'title',
            'excerpt' => 'excerpt',
            'imageAlt' => 'image_alt',
            'categoryId' => 'category_id',
            'authorId' => 'author_id',
            'tags' => 'tags',
            'seoTitle' => 'seo_title',
            'metaDescription' => 'meta_description',
            'status' => 'status',
        ];

        $attributes = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }
        if (array_key_exists('content', $data)) {
            $attributes['content'] = $this->sanitizer->clean($data['content']);
            $attributes['reading_minutes'] = Blog::readingMinutesFor($attributes['content']);
        }
        if (array_key_exists('tags', $attributes)) {
            $attributes['tags'] = array_values($attributes['tags'] ?? []);
        }

        return $attributes;
    }

    private function actionFor(bool $wasPublished, bool $isPublished): ActivityAction
    {
        return match (true) {
            ! $wasPublished && $isPublished => ActivityAction::Published,
            $wasPublished && ! $isPublished => ActivityAction::Unpublished,
            default => ActivityAction::Updated,
        };
    }
}
