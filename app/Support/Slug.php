<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slug
{
    /**
     * Returns a slug that no other row uses: "my-post", then "my-post-2", ...
     *
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $source, ?int $ignoreId = null, int $maxLength = 140): string
    {
        $base = Str::limit(Str::slug($source), $maxLength - 4, '') ?: 'item';
        $base = rtrim($base, '-');

        $taken = $model::query()
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('slug')
            ->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $i = 2;
        while (in_array("{$base}-{$i}", $taken, true)) {
            $i++;
        }

        return "{$base}-{$i}";
    }
}
