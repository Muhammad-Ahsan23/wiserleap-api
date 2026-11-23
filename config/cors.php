<?php

/*
| Only the Next.js site may call this API from a browser. Credentials are
| allowed because the admin panel uses Sanctum's cookie authentication.
| FRONTEND_URLS: comma-separated, exact origins (scheme + host + port).
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('FRONTEND_URLS', 'http://localhost:3000'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => 7200,

    'supports_credentials' => true,

];
