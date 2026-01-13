<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Presence TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a user is considered online after their last
    | activity. Default is 60 seconds.
    |
    */
    'ttl_seconds' => env('PRESENCE_TTL_SEC', 60),

    /*
    |--------------------------------------------------------------------------
    | Typing Indicator TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a typing indicator persists without updates.
    | Default is 5 seconds.
    |
    */
    'typing_ttl_seconds' => env('TYPING_TTL_SEC', 5),
];
