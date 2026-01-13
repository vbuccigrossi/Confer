<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Metrics Enabled
    |--------------------------------------------------------------------------
    |
    | Enable the /metrics endpoint for Prometheus scraping.
    |
    */
    'enabled' => env('METRICS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Basic Auth Protection
    |--------------------------------------------------------------------------
    |
    | Require HTTP Basic Authentication for /metrics endpoint.
    |
    */
    'basic_auth' => env('METRICS_BASIC_AUTH', false),
    'basic_auth_user' => env('METRICS_BASIC_AUTH_USER', 'metrics'),
    'basic_auth_password' => env('METRICS_BASIC_AUTH_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Metric Prefixes
    |--------------------------------------------------------------------------
    |
    | Prefix for all Prometheus metrics.
    |
    */
    'prefix' => 'latch',

    /*
    |--------------------------------------------------------------------------
    | Cache Keys
    |--------------------------------------------------------------------------
    |
    | Redis keys for storing metric values.
    |
    */
    'redis_keys' => [
        'requests_total' => 'metrics:requests:total',
        'queue_jobs_processed' => 'metrics:queue:jobs:processed',
    ],
];
