<?php

return [

    /*
    | Disk for uploaded images (blog covers, author photos). "public" stores
    | them in storage/app/public (run `php artisan storage:link`). Use an
    | S3-compatible disk (e.g. Cloudflare R2) by setting MEDIA_DISK=s3.
    */
    'disk' => env('MEDIA_DISK', 'public'),

    // Upload limits (kilobytes) - match the admin panel.
    'blog_image_max_kb' => (int) env('BLOG_IMAGE_MAX_KB', 2048),
    'author_image_max_kb' => (int) env('AUTHOR_IMAGE_MAX_KB', 800),

    // How long public blog responses are cached (minutes).
    'public_cache_minutes' => (int) env('PUBLIC_CACHE_MINUTES', 10),

];
