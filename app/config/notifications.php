<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Digest Delay
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) a user must be offline before receiving
    | an email digest of unread mentions/replies. Default is 15 minutes.
    |
    */
    'email_delay_minutes' => env('MENTION_EMAIL_DELAY_MIN', 15),

    /*
    |--------------------------------------------------------------------------
    | Web Push Configuration
    |--------------------------------------------------------------------------
    |
    | Enable web push notifications (disabled by default).
    |
    */
    'enable_web_push' => env('ENABLE_WEB_PUSH', false),

    'web_push' => [
        'vapid_public' => env('VAPID_PUBLIC_KEY'),
        'vapid_private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('WEB_PUSH_SUBJECT', 'mailto:ops@example.com'),
    ],
];
