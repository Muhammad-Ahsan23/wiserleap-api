<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded images on the configured media disk (local "public" disk by
 * default, or any S3-compatible disk such as Cloudflare R2 in production).
 */
class Media
{
    public static function disk(): string
    {
        return config('media.disk');
    }

    /** Saves the file under a random name and returns its relative path. */
    public static function store(UploadedFile $file, string $folder): string
    {
        $extension = $file->guessExtension() ?: 'jpg';
        $name = Str::uuid()->toString().'.'.$extension;

        return $file->storeAs($folder.'/'.now()->format('Y/m'), $name, ['disk' => self::disk(), 'visibility' => 'public']);
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::disk())->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::disk())->url($path) : null;
    }
}
