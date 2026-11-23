<?php

return [

    // Next.js site that uses this API (password reset links point here).
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    /*
    | Sign-up from the admin panel. The very first account can always be
    | created (so you can set up the site); after that, sign-up is closed
    | unless this is set to true. You can also run `php artisan admin:create`.
    */
    'registration_enabled' => (bool) env('ADMIN_REGISTRATION_ENABLED', false),

];
