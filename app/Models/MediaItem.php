<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** An image in the media library (used inside blog content). */
#[Fillable(['path', 'name', 'alt', 'mime', 'size', 'width', 'height', 'user_id'])]
class MediaItem extends Model
{
    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return (string) Media::url($this->path);
    }

    /** Blogs whose content shows this image. */
    public function usedIn(): Collection
    {
        $like = '%'.addcslashes($this->path, '%_\\').'%';

        return Blog::query()->where('content', 'like', $like)->orderBy('title')->get(['id', 'title']);
    }
}
