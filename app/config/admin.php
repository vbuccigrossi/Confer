<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Log Export Settings
    |--------------------------------------------------------------------------
    */
    'audit_log_export_max_rows' => env('AUDIT_LOG_EXPORT_MAX_ROWS', 100000),

    /*
    |--------------------------------------------------------------------------
    | Default Workspace Settings
    |--------------------------------------------------------------------------
    */
    'default_message_retention_days' => env('DEFAULT_MESSAGE_RETENTION_DAYS', 0), // 0 = unlimited
    'default_workspace_quota_mb' => env('DEFAULT_WORKSPACE_QUOTA_MB', 1024),

    /*
    |--------------------------------------------------------------------------
    | Retention Purge Settings
    |--------------------------------------------------------------------------
    */
    'purge_batch_size' => env('PURGE_BATCH_SIZE', 1000),
];
