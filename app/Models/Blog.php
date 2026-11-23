<?php

namespace App\Models;

use App\Enums\BlogStatus;
use App\Support\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title', 'slug', 'excerpt', 'content', 'image_path', 'image_alt',
    'category_id', 'author_id', 'tags', 'seo_title', 'meta_description',
    'status', 'published_at', 'reading_minutes',
])]
class Blog extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BlogStatus::class,
            'tags' => 'array',
            'category_id' => 'integer',
            'author_id' => 'integer',
            'views' => 'integer',
            'reading_minutes' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', BlogStatus::Published)->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === BlogStatus::Published;
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image_path);
    }

    /** Reading time from the plain text (200 words per minute), saved with the post. */
    public static function readingMinutesFor(?string $html): int
    {
        $words = str_word_count(strip_tags((string) $html));

        return max(1, (int) ceil($words / 200));
    }
}
