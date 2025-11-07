<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ZKTeco Device Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to ZKTeco biometric attendance device.
    | Make sure the device is accessible on your network.
    |
    */

    'device_ip' => env('ZKTECO_DEVICE_IP', '192.168.1.201'),
    'device_port' => env('ZKTECO_DEVICE_PORT', 4370),

    /*
    |--------------------------------------------------------------------------
    | Remote API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the remote server where attendance data will be sent.
    |
    */

    'remote_api_url' => env('REMOTE_API_URL', 'https://api.example.com/api/v1'),
    'remote_api_key' => env('REMOTE_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Additional settings for API communication.
    |
    */

    'api_timeout' => env('REMOTE_API_TIMEOUT', 30), // seconds

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | Settings for synchronization process.
    |
    */

    'batch_size' => env('SYNC_BATCH_SIZE', 100), // Records per batch
    'auto_clear_device' => env('AUTO_CLEAR_DEVICE', false), // Auto-clear after successful sync
    'retry_failed' => env('RETRY_FAILED_RECORDS', true), // Retry failed records
    'max_retries' => env('MAX_RETRIES', 3), // Maximum retry attempts

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging for debugging.
    |
    */

    'enable_debug_logging' => env('ZKTECO_DEBUG_LOGGING', false),

];
