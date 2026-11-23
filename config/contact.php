<?php

return [

    /*
    | Shared secret between the Next.js server and this API. The website's
    | contact form is submitted by the Next.js server (never by the browser)
    | with this key in the X-Site-Key header. Empty = the endpoint is closed.
    | Generate one with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    */
    'site_key' => env('CONTACT_SITE_KEY', ''),

    // Inbox that receives new inquiries (reply-to is set to the visitor).
    'notify_email' => env('CONTACT_NOTIFY_EMAIL', 'hello@wiserleap.com'),

    // Name shown on replies sent from the dashboard (address = MAIL_FROM_ADDRESS).
    'reply_from_name' => env('CONTACT_REPLY_FROM_NAME', 'WiserLeap'),

    // The same email + message within this many minutes is treated as a resubmit.
    'duplicate_window_minutes' => 10,

];
