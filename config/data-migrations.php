<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data Migrations Path
    |--------------------------------------------------------------------------
    |
    | The directory where data migration files are stored. By default, they
    | are stored in database/data-migrations to keep them separate from
    | schema migrations.
    |
    */
    'path' => database_path('data-migrations'),

    /*
    |--------------------------------------------------------------------------
    | Data Migrations Table
    |--------------------------------------------------------------------------
    |
    | The table name used to track which data migrations have been run.
    | This is separate from Laravel's schema migrations table.
    |
    */
    'table' => 'data_migrations',

    /*
    |--------------------------------------------------------------------------
    | Default Chunk Size
    |--------------------------------------------------------------------------
    |
    | When processing large datasets, migrations can be chunked to avoid
    | memory issues. This is the default chunk size used when not specified
    | in the migration itself.
    |
    */
    'chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Default Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time in seconds for a single data migration.
    | Set to 0 for unlimited. Individual migrations can override this.
    |
    */
    'timeout' => 0,

    /*
    |--------------------------------------------------------------------------
    | Transaction Mode
    |--------------------------------------------------------------------------
    |
    | Whether to wrap migrations in a database transaction by default.
    | Options: 'auto', 'always', 'never'
    | - auto: Use transaction unless migration explicitly disables it
    | - always: Always use transaction (may fail for large datasets)
    | - never: Never use transaction
    |
    */
    'transaction' => 'auto',

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for data migrations.
    |
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('DATA_MIGRATIONS_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Checks
    |--------------------------------------------------------------------------
    |
    | Safety features to prevent accidental data loss.
    |
    */
    'safety' => [
        // Require --force flag in production
        'require_force_in_production' => true,

        // Maximum rows that can be affected without confirmation
        'confirm_threshold' => 10000,

        // Automatically backup affected tables (requires spatie/laravel-backup)
        'auto_backup' => false,
    ],
];
