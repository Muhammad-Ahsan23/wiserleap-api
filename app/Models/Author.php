<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'bio', 'image_path'])]
class Author extends Model
{
    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image_path);
    }
}
