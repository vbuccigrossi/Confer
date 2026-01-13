<?php

return [
    /*
    |--------------------------------------------------------------------------
    | App Integration Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of requests per minute for webhook and slash command
    | endpoints. Protects against abuse and ensures fair usage.
    |
    */
    'rate_limit_per_minute' => env('APP_INTEGRATION_RATE_PER_MIN', 30),

    /*
    |--------------------------------------------------------------------------
    | Outbox Maximum Retries
    |--------------------------------------------------------------------------
    |
    | Maximum number of delivery attempts for outbox events before marking
    | them as permanently failed.
    |
    */
    'outbox_max_retries' => env('APP_OUTBOX_MAX_RETRIES', 6),

    /*
    |--------------------------------------------------------------------------
    | Outbox Backoff Schedule
    |--------------------------------------------------------------------------
    |
    | Exponential backoff schedule in seconds for retry attempts.
    | Example: [1, 5, 30, 120, 300, 600] means:
    | - 1st retry: after 1 second
    | - 2nd retry: after 5 seconds
    | - 3rd retry: after 30 seconds
    | - 4th retry: after 2 minutes
    | - 5th retry: after 5 minutes
    | - 6th retry: after 10 minutes
    |
    */
    'outbox_backoff_schedule' => [1, 5, 30, 120, 300, 600],

    /*
    |--------------------------------------------------------------------------
    | Token Length
    |--------------------------------------------------------------------------
    |
    | Length of generated app tokens for security purposes.
    |
    */
    'token_length' => 64,

    /*
    |--------------------------------------------------------------------------
    | Webhook HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds to wait for webhook delivery response.
    |
    */
    'webhook_timeout' => 10,
];
