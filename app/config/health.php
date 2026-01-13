<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Readiness Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in milliseconds to wait for database connectivity check
    | during readiness probe. Lower values fail faster but may give false
    | negatives on slow networks.
    |
    */
    'ready_db_timeout_ms' => env('HEALTH_READY_DB_TIMEOUT_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | Redis Readiness Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in milliseconds to wait for Redis connectivity check
    | during readiness probe.
    |
    */
    'ready_redis_timeout_ms' => env('HEALTH_READY_REDIS_TIMEOUT_MS', 500),
];
