<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub Organization
    |--------------------------------------------------------------------------
    | The GitHub organization login name to pull Copilot metrics for.
    */
    'org' => env('GITHUB_ORG', ''),

    /*
    |--------------------------------------------------------------------------
    | GitHub App Credentials
    |--------------------------------------------------------------------------
    | Used by the backend to authenticate against the Copilot metrics API.
    | Either set GITHUB_APP_PRIVATE_KEY_PATH to a file path, or
    | GITHUB_APP_PRIVATE_KEY to the raw PEM content.
    */
    'github_app_id'             => env('GITHUB_APP_ID', ''),
    'github_app_installation_id' => env('GITHUB_APP_INSTALLATION_ID', ''),
    'github_app_private_key_path' => env('GITHUB_APP_PRIVATE_KEY_PATH', ''),
    'github_app_private_key'    => env('GITHUB_APP_PRIVATE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Admin Logins
    |--------------------------------------------------------------------------
    | GitHub logins (comma-separated) that are always treated as admins,
    | in addition to org owners detected automatically.
    */
    'admin_logins' => array_filter(
        explode(',', env('COPILOT_ADMIN_LOGINS', '')),
        fn ($v) => $v !== ''
    ),

    /*
    |--------------------------------------------------------------------------
    | Dev auto-login
    |--------------------------------------------------------------------------
    | In NON-PRODUCTION environments only, auto-authenticate as the user with
    | this GitHub login so the dashboard can be accessed without the OAuth
    | flow. Leave empty to disable. Ignored entirely in production.
    */
    'dev_login' => env('COPILOT_DEV_LOGIN'),

    /*
    |--------------------------------------------------------------------------
    | Sync schedule
    |--------------------------------------------------------------------------
    | Time (H:i, UTC) at which the daily sync runs.
    */
    'sync_time' => env('COPILOT_SYNC_TIME', '06:00'),
];
