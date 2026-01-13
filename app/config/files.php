<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for file uploads, storage, and access control.
    |
    */

    /**
     * Maximum upload size in megabytes
     */
    'max_upload_size_mb' => env('MAX_UPLOAD_SIZE_MB', 64),

    /**
     * Workspace storage quota in megabytes
     */
    'workspace_quota_mb' => env('WORKSPACE_QUOTA_MB', 1024),

    /**
     * Signed URL time-to-live in minutes
     */
    'sign_ttl_minutes' => env('FILE_SIGN_TTL_MIN', 15),

    /**
     * Enable virus scanning (requires ClamAV)
     */
    'enable_virus_scan' => env('ENABLE_VIRUS_SCAN', false),

    /**
     * Allowed MIME types for uploads
     */
    'allowed_mimes' => [
        // Images
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'image/webp',
        // Documents
        'application/pdf',
        'text/plain',
        'text/markdown',
        // Archives
        'application/zip',
        'application/x-zip-compressed',
    ],

    /**
     * Thumbnail settings
     */
    'thumbnail' => [
        'max_width' => 512,
        'max_height' => 512,
        'quality' => 80,
    ],

    /**
     * Storage disk for uploads
     */
    'disk' => env('FILESYSTEM_DISK', 'local'),

];
