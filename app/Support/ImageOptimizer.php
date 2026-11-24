<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Library images: re-encoded as WebP (smaller files, and the re-encode drops
 * EXIF data such as GPS location), scaled down to MAX_WIDTH, JPEG photos
 * turned upright. Falls back to storing the original when GD/WebP is missing.
 */
class ImageOptimizer
{
    public const MAX_WIDTH = 2000;

    private const QUALITY = 82;

    /** @return array{path: string, mime: string, size: int, width: int, height: int} */
    public static function store(UploadedFile $file, string $folder): array
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info) {
            throw new \RuntimeException('Not a readable image.');
        }

        $optimized = self::canOptimize() ? self::toWebp($file->getRealPath(), $info[2]) : null;

        if ($optimized === null) {
            $path = Media::store($file, $folder);

            return ['path' => $path, 'mime' => $info['mime'], 'size' => (int) $file->getSize(), 'width' => $info[0], 'height' => $info[1]];
        }

        [$bytes, $width, $height] = $optimized;
        $path = $folder.'/'.now()->format('Y/m').'/'.Str::uuid()->toString().'.webp';
        Storage::disk(Media::disk())->put($path, $bytes, ['visibility' => 'public']);

        return ['path' => $path, 'mime' => 'image/webp', 'size' => strlen($bytes), 'width' => $width, 'height' => $height];
    }

    public static function canOptimize(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /** @return array{0: string, 1: int, 2: int}|null */
    private static function toWebp(string $file, int $type): ?array
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
        if (! $image) {
            return null;
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = self::upright($image, $file);
        }

        imagepalettetotruecolor($image);
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > self::MAX_WIDTH) {
            $newHeight = max(1, (int) round($height * self::MAX_WIDTH / $width));
            $resized = imagecreatetruecolor(self::MAX_WIDTH, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, self::MAX_WIDTH, $newHeight, $width, $height);
            imagedestroy($image);
            [$image, $width, $height] = [$resized, self::MAX_WIDTH, $newHeight];
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $ok && $bytes !== '' ? [$bytes, $width, $height] : null;
    }

    /** Phone photos store their rotation in EXIF; apply it before EXIF is dropped. */
    private static function upright(\GdImage $image, string $file): \GdImage
    {
        $orientation = function_exists('exif_read_data') ? (int) (@exif_read_data($file)['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }
}
