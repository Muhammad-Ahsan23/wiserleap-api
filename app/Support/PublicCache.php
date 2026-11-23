<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for the public blog endpoints. Every write in the admin
 * bumps a version number, so visitors never see stale posts and no cache
 * tags are needed (works with the file, database and Redis cache stores).
 */
class PublicCache
{
    private const VERSION_KEY = 'public-blog:version';

    public static function remember(string $key, Closure $callback): mixed
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return Cache::remember("public-blog:v{$version}:{$key}", now()->addMinutes(config('media.public_cache_minutes')), $callback);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Cache::get(self::VERSION_KEY, 1) + 1);
    }
}
