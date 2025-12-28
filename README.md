# Laravel Data Migrations

> **[Lire en Français](README-FR.md)**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vherbaut/laravel-data-migrations.svg?style=flat-square)](https://packagist.org/packages/vherbaut/laravel-data-migrations)
[![Tests](https://img.shields.io/github/actions/workflow/status/vherbaut/laravel-data-migrations/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/vherbaut/laravel-data-migrations/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![Total Downloads](https://img.shields.io/packagist/dt/vherbaut/laravel-data-migrations.svg?style=flat-square)](https://packagist.org/packages/vherbaut/laravel-data-migrations)
[![License](https://img.shields.io/packagist/l/vherbaut/laravel-data-migrations.svg?style=flat-square)](https://packagist.org/packages/vherbaut/laravel-data-migrations)

**Versioned data migrations for Laravel.** Transform, backfill, and migrate your data with the same elegance as schema migrations.

---

## Table of Contents

- [Why Data Migrations?](#why-data-migrations)
- [Data Migrations vs Seeders](#data-migrations-vs-seeders)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Console Commands](#console-commands)
- [Writing Data Migrations](#writing-data-migrations)
- [Configuration](#configuration)
- [Safety Features](#safety-features)
- [Real-World Examples](#real-world-examples)
- [Architecture](#architecture)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Contributing](#contributing)
- [License](#license)

---

## Why Data Migrations?

Laravel's schema migrations handle database structure changes beautifully, but what about data transformations? Currently, developers resort to:

- **Putting data logic in schema migrations** — Mixing concerns, hard to rollback
- **One-off artisan commands** — Not versioned, forgotten, impossible to replay
- **Manual SQL in production** — Dangerous and undocumented

**Data Migrations** solve this by providing a structured, versioned approach to data transformations.

---

## Data Migrations vs Seeders

A common question: *"Why not just use Laravel Seeders?"*

Seeders and Data Migrations serve **fundamentally different purposes**:

| Aspect | Seeders | Data Migrations |
|--------|---------|-----------------|
| **Purpose** | Populate dev/test data | Transform production data |
| **Environment** | Development, testing | Production, staging |
| **Tracking** | None - can run multiple times | Versioned - runs once per environment |
| **Rollback** | Not supported | Full rollback support |
| **History** | No record of execution | Complete audit trail (when, rows affected, duration) |
| **Team sync** | Manual coordination | Automatic - like schema migrations |
| **Progress** | No feedback | Progress bars, row counts |
| **Safety** | No safeguards | Dry-run, confirmations, backups* |

_*Backup feature requires [spatie/laravel-backup](https://github.com/spatie/laravel-backup)_

### When to use Seeders

```php
// Seeders: Populate test data for development
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->count(100)->create();  // Creates fake users
    }
}
```

Use seeders when you need to:
- Generate fake data for local development
- Reset your database to a known state
- Create test fixtures

### When to use Data Migrations

```php
// Data Migrations: Transform real production data
return new class extends DataMigration
{
    protected string $description = 'Migrate legacy status values to new enum';

    public function up(): void
    {
        // Transforms existing production data
        DB::table('orders')
            ->where('status', 'pending_payment')
            ->update(['status' => 'awaiting_payment']);

        $this->affected(DB::table('orders')->where('status', 'awaiting_payment')->count());
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('status', 'awaiting_payment')
            ->update(['status' => 'pending_payment']);
    }
};
```

Use data migrations when you need to:
- Transform existing production data
- Backfill new columns with calculated values
- Normalize or clean up legacy data
- Migrate data between schema changes
- Ensure all team members/environments apply the same data changes

### The Problem with Using Seeders for Data Transformations

```php
// DON'T DO THIS - Using seeders for production data changes
class FixUserEmailsSeeder extends Seeder
{
    public function run(): void
    {
        // Problems:
        // 1. No tracking - might run twice and corrupt data
        // 2. No rollback if something goes wrong
        // 3. No audit trail - when was this run? By whom?
        // 4. Team members don't know if they need to run it
        // 5. No progress feedback on large datasets
        DB::table('users')->update([
            'email' => DB::raw('LOWER(email)')
        ]);
    }
}
```

**Data Migrations solve all these problems** by treating data changes with the same rigor as schema changes.

---

## Features

| Feature | Description |
|---------|-------------|
| **Versioned Migrations** | Track data changes just like schema migrations |
| **Separate from Schema** | Keep data logic independent from structure changes |
| **Rollback Support** | Reverse data changes when needed |
| **Dry-Run Mode** | Preview what will happen before execution |
| **Progress Tracking** | Visual progress bars for long-running operations |
| **Chunked Processing** | Process millions of rows without memory issues |
| **Production Safety** | Built-in confirmations and force flags |
| **Transaction Support** | Automatic transaction wrapping with configurable modes |
| **Auto Backup** | Optional automatic backup before migrations (requires [spatie/laravel-backup](https://github.com/spatie/laravel-backup)) |
| **Timeout Control** | Configurable execution time limits |
| **Row Threshold Alerts** | Confirmation prompts for large operations |
| **PHPStan Level 5** | Fully typed, strict static analysis compliance |

---

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- A supported database (MySQL, PostgreSQL, SQLite, SQL Server)

---

## Installation

Install the package via Composer:

```bash
composer require vherbaut/laravel-data-migrations
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=data-migrations-config
```

Run the migrations to create the tracking table:

```bash
php artisan migrate
```

### Optional: Publish Stubs

Customize the migration templates:

```bash
php artisan vendor:publish --tag=data-migrations-stubs
```

---

## Quick Start

### 1. Create a Data Migration

```bash
php artisan make:data-migration split_user_names
```

This creates `database/data-migrations/2024_01_15_123456_split_user_names.php`:

```php
<?php

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration
{
    protected string $description = 'Split full_name into first_name and last_name';

    protected array $affectedTables = ['users'];

    public function up(): void
    {
        DB::table('users')
            ->whereNull('first_name')
            ->cursor()
            ->each(function ($user) {
                $parts = explode(' ', $user->full_name, 2);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $parts[0],
                        'last_name' => $parts[1] ?? '',
                    ]);

                $this->affected();
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('first_name')
            ->update([
                'full_name' => DB::raw("CONCAT(first_name, ' ', last_name)"),
                'first_name' => null,
                'last_name' => null,
            ]);
    }
};
```

### 2. Run Migrations

```bash
# Run pending data migrations
php artisan data:migrate

# Preview changes without executing (dry run)
php artisan data:migrate --dry-run

# Force execution in production
php artisan data:migrate --force
```

### 3. Check Status

```bash
php artisan data:status
```

```
+--------------------------------------+-------+-----------+--------+----------+---------------------+
| Migration                            | Batch | Status    | Rows   | Duration | Ran At              |
+--------------------------------------+-------+-----------+--------+----------+---------------------+
| 2024_01_15_123456_split_user_names   | 1     | Completed | 50,000 | 4523ms   | 2024-01-15 12:35:00 |
| 2024_01_16_091500_normalize_phones   | -     | Pending   | -      | -        | -                   |
+--------------------------------------+-------+-----------+--------+----------+---------------------+

Total: 2 | Pending: 1 | Completed: 1 | Failed: 0
```

---

## Console Commands

| Command | Description |
|---------|-------------|
| `make:data-migration {name}` | Create a new data migration file |
| `data:migrate` | Run all pending data migrations |
| `data:rollback` | Rollback the last batch of migrations |
| `data:status` | Display the status of all migrations |
| `data:fresh` | Reset and re-run all data migrations |

### make:data-migration

Create a new data migration file.

```bash
php artisan make:data-migration {name} [options]
```

| Option | Description |
|--------|-------------|
| `--table=` | Specify the table being migrated |
| `--chunked` | Use the chunked migration template |
| `--idempotent` | Mark the migration as idempotent |

**Examples:**

```bash
# Basic migration
php artisan make:data-migration update_user_statuses

# Chunked migration for large datasets
php artisan make:data-migration process_orders --table=orders --chunked

# Idempotent migration (safe to re-run)
php artisan make:data-migration normalize_emails --idempotent
```

### data:migrate

Run pending data migrations.

```bash
php artisan data:migrate [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview migrations without executing |
| `--force` | Force execution in production environment |
| `--step` | Run migrations one at a time |
| `--no-confirm` | Skip row count confirmation prompts |

### data:rollback

Rollback data migrations.

```bash
php artisan data:rollback [options]
```

| Option | Description |
|--------|-------------|
| `--step=N` | Rollback the last N migrations |
| `--batch=N` | Rollback a specific batch number |
| `--force` | Force execution in production environment |

**Examples:**

```bash
# Rollback the last batch
php artisan data:rollback

# Rollback the last 3 migrations
php artisan data:rollback --step=3

# Rollback batch number 2
php artisan data:rollback --batch=2
```

### data:status

Display the status of all data migrations.

```bash
php artisan data:status [options]
```

| Option | Description |
|--------|-------------|
| `--pending` | Only show pending migrations |
| `--ran` | Only show completed migrations |

### data:fresh

Reset and re-run all data migrations.

```bash
php artisan data:fresh [options]
```

| Option | Description |
|--------|-------------|
| `--force` | Force execution in production environment |
| `--seed` | Run seeders after migrations (reserved) |

> **Warning:** This command will delete all migration records and re-run every migration. Use with caution.

---

## Writing Data Migrations

### Migration Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$description` | `string` | `''` | Human-readable description of what this migration does |
| `$affectedTables` | `array` | `[]` | List of tables this migration modifies (for documentation/backup) |
| `$withinTransaction` | `bool` | `true` | Whether to wrap the migration in a database transaction |
| `$chunkSize` | `int` | `1000` | Default chunk size for chunked operations |
| `$idempotent` | `bool` | `false` | Whether this migration is safe to run multiple times |
| `$connection` | `?string` | `null` | Database connection to use (null = default) |
| `$timeout` | `?int` | `0` | Maximum execution time in seconds (0 = use config, null = unlimited) |

### Basic Migration

```php
return new class extends DataMigration
{
    protected string $description = 'Deactivate users who haven\'t logged in for a year';

    protected array $affectedTables = ['users'];

    public function up(): void
    {
        $affected = DB::table('users')
            ->where('status', 'active')
            ->where('last_login_at', '<', now()->subYear())
            ->update(['status' => 'inactive']);

        $this->affected($affected);
    }
};
```

### Chunked Migration (Large Datasets)

For large datasets, use chunked processing to avoid memory issues and long-running transactions:

```php
return new class extends DataMigration
{
    protected string $description = 'Recalculate order totals';

    protected array $affectedTables = ['orders'];

    protected int $chunkSize = 500;

    protected bool $withinTransaction = false; // Important for large datasets

    public function up(): void
    {
        $total = $this->getEstimatedRows();
        $this->startProgress($total, "Processing {$total} orders...");

        $this->chunk('orders', function ($order) {
            $newTotal = DB::table('order_items')
                ->where('order_id', $order->id)
                ->sum('price');

            DB::table('orders')
                ->where('id', $order->id)
                ->update(['total' => $newTotal]);
        });

        $this->finishProgress();
    }

    public function getEstimatedRows(): ?int
    {
        return DB::table('orders')->count();
    }
};
```

### Idempotent Migration

Migrations that are safe to run multiple times:

```php
return new class extends DataMigration
{
    protected string $description = 'Normalize email addresses to lowercase';

    protected bool $idempotent = true;

    public function up(): void
    {
        // Only process records that haven't been normalized
        DB::table('users')
            ->whereRaw('email != LOWER(email)')
            ->cursor()
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['email' => strtolower($user->email)]);

                $this->affected();
            });
    }
};
```

### Reversible Migration

Implement `down()` to enable rollback:

```php
return new class extends DataMigration
{
    protected string $description = 'Apply 10% price increase';

    protected array $affectedTables = ['products'];

    public function up(): void
    {
        $affected = DB::table('products')
            ->update(['price' => DB::raw('price * 1.1')]);

        $this->affected($affected);
    }

    public function down(): void
    {
        DB::table('products')
            ->update(['price' => DB::raw('price / 1.1')]);
    }
};
```

### Using a Specific Database Connection

```php
return new class extends DataMigration
{
    protected ?string $connection = 'tenant';

    public function up(): void
    {
        $this->db()->table('settings')->update(['migrated' => true]);
    }
};
```

### Setting Execution Timeout

```php
return new class extends DataMigration
{
    protected ?int $timeout = 3600; // 1 hour maximum

    public function up(): void
    {
        // Long-running operation...
    }
};
```

---

## Available Methods

### Database Access

```php
// Get the configured database connection
$this->db()->table('users')->get();
```

### Progress Tracking

```php
// Start a progress bar
$this->startProgress(1000, 'Processing records...');

// Increment by 1
$this->incrementProgress();

// Increment by N
$this->addProgress(10);

// Set absolute progress
$this->setProgress(500);

// Finish and clear the progress bar
$this->finishProgress();

// Get current percentage
$percentage = $this->getProgressPercentage();
```

### Chunk Processing

```php
// Process records one at a time
$processed = $this->chunk('table_name', function ($record) {
    // Process each record
    // Progress is automatically incremented
});

// Memory-efficient lazy iteration
$processed = $this->chunkLazy('table_name', function ($record) {
    // Process each record
});

// Mass update in chunks (for UPDATE queries)
$affected = $this->chunkUpdate(
    'table_name',
    ['status' => 'processed'],
    function ($query) {
        $query->where('status', 'pending');
    }
);
```

### Row Counting

```php
// Increment affected rows by 1
$this->affected();

// Increment by a specific amount
$this->affected(100);

// Get total affected rows (used in logging)
$count = $this->getRowsAffected();
```

### Console Output

```php
// Info message (console only)
$this->info('Processing complete!');

// Warning message
$this->warn('Some records were skipped.');

// Error message
$this->error('Failed to process record.');

// Log message (to configured log channel + console)
$this->log('Migration completed successfully.');
$this->log('An error occurred.', 'error');
```

### Dry Run Information

Override `dryRun()` to provide detailed information during `--dry-run`:

```php
public function dryRun(): array
{
    return [
        'description' => $this->getDescription(),
        'affected_tables' => $this->affectedTables,
        'estimated_rows' => $this->getEstimatedRows(),
        'reversible' => $this->isReversible(),
        'idempotent' => $this->idempotent,
        'uses_transaction' => $this->withinTransaction,
    ];
}
```

---

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=data-migrations-config
```

### Full Configuration Reference

```php
<?php
// config/data-migrations.php

return [
    /*
    |--------------------------------------------------------------------------
    | Migration Path
    |--------------------------------------------------------------------------
    |
    | The directory where data migration files are stored.
    |
    */
    'path' => database_path('data-migrations'),

    /*
    |--------------------------------------------------------------------------
    | Migration Table
    |--------------------------------------------------------------------------
    |
    | The database table used to track which migrations have run.
    |
    */
    'table' => 'data_migrations',

    /*
    |--------------------------------------------------------------------------
    | Default Chunk Size
    |--------------------------------------------------------------------------
    |
    | The default number of records to process per chunk.
    |
    */
    'chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Transaction Mode
    |--------------------------------------------------------------------------
    |
    | How to handle database transactions:
    | - 'auto': Use migration's $withinTransaction property
    | - 'always': Always wrap in transaction (overrides migration setting)
    | - 'never': Never use transactions (overrides migration setting)
    |
    */
    'transaction' => 'auto',

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time in seconds. Set to 0 or null for no limit.
    | Individual migrations can override this with the $timeout property.
    |
    */
    'timeout' => 0,

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('DATA_MIGRATIONS_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Configuration
    |--------------------------------------------------------------------------
    */
    'safety' => [
        /*
        | Require --force flag when running in production
        */
        'require_force_in_production' => true,

        /*
        | Ask for confirmation if estimated rows exceed this threshold.
        | Set to 0 to disable.
        */
        'confirm_threshold' => 10000,

        /*
        | Automatically create a database backup before running migrations.
        | Requires spatie/laravel-backup package.
        */
        'auto_backup' => false,
    ],
];
```

---

## Safety Features

### Production Protection

By default, running migrations in production requires the `--force` flag:

```bash
# This will prompt for confirmation in production
php artisan data:migrate

# This will run without prompting
php artisan data:migrate --force
```

### Row Count Confirmation

When a migration estimates it will affect more rows than `confirm_threshold`, you'll be prompted:

```
Estimated rows to be affected: 150,000
This exceeds the confirmation threshold of 10,000 rows.
Do you wish to continue? (yes/no) [no]:
```

Skip with `--no-confirm` or `--force`:

```bash
php artisan data:migrate --no-confirm
```

### Auto Backup

Enable automatic database backup before migrations (requires [spatie/laravel-backup](https://github.com/spatie/laravel-backup)):

```bash
composer require spatie/laravel-backup
```

```php
// config/data-migrations.php
'safety' => [
    'auto_backup' => true,
],
```

### Timeout Protection

Prevent runaway migrations with timeout limits:

```php
// config/data-migrations.php
'timeout' => 300, // 5 minutes global limit

// Or per-migration
protected ?int $timeout = 600; // 10 minutes for this migration
```

---

## Real-World Examples

### Normalize Phone Numbers

```php
return new class extends DataMigration
{
    protected string $description = 'Normalize phone numbers to E.164 format';

    protected array $affectedTables = ['users'];

    protected bool $idempotent = true;

    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', 'NOT LIKE', '+%')
            ->cursor()
            ->each(function ($user) {
                $normalized = $this->normalizePhone($user->phone);

                if ($normalized) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['phone' => $normalized]);

                    $this->affected();
                }
            });
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 10 ? '+1' . $digits : null;
    }
};
```

### Backfill Calculated Fields

```php
return new class extends DataMigration
{
    protected string $description = 'Backfill order_count on customers';

    protected array $affectedTables = ['customers'];

    protected bool $withinTransaction = false;

    public function up(): void
    {
        $total = DB::table('customers')->whereNull('order_count')->count();
        $this->startProgress($total);

        $this->chunkUpdate(
            'customers',
            ['order_count' => DB::raw('(SELECT COUNT(*) FROM orders WHERE orders.customer_id = customers.id)')],
            fn ($query) => $query->whereNull('order_count')
        );

        $this->finishProgress();
    }
};
```

### GDPR Data Anonymization

```php
return new class extends DataMigration
{
    protected string $description = 'Anonymize users deleted more than 2 years ago (GDPR)';

    protected array $affectedTables = ['users'];

    protected bool $idempotent = true;

    public function up(): void
    {
        DB::table('users')
            ->where('deleted_at', '<', now()->subYears(2))
            ->whereNull('anonymized_at')
            ->cursor()
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => "anonymized_{$user->id}@deleted.local",
                        'name' => 'Deleted User',
                        'phone' => null,
                        'address' => null,
                        'anonymized_at' => now(),
                    ]);

                $this->affected();
            });
    }
};
```

### Encrypt Sensitive Data

```php
return new class extends DataMigration
{
    protected string $description = 'Encrypt SSN field';

    protected array $affectedTables = ['employees'];

    protected bool $withinTransaction = false;

    public function up(): void
    {
        $total = DB::table('employees')
            ->whereNotNull('ssn')
            ->whereNull('ssn_encrypted')
            ->count();

        $this->startProgress($total, 'Encrypting SSN data...');

        DB::table('employees')
            ->whereNotNull('ssn')
            ->whereNull('ssn_encrypted')
            ->cursor()
            ->each(function ($employee) {
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update([
                        'ssn_encrypted' => encrypt($employee->ssn),
                        'ssn' => null,
                    ]);

                $this->incrementProgress();
                $this->affected();
            });

        $this->finishProgress();
    }
};
```

### Migrate to New Schema Structure

```php
return new class extends DataMigration
{
    protected string $description = 'Migrate addresses from users to addresses table';

    protected array $affectedTables = ['users', 'addresses'];

    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('address_line1')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('addresses')
                    ->whereRaw('addresses.user_id = users.id');
            })
            ->cursor()
            ->each(function ($user) {
                DB::table('addresses')->insert([
                    'user_id' => $user->id,
                    'line1' => $user->address_line1,
                    'line2' => $user->address_line2,
                    'city' => $user->city,
                    'state' => $user->state,
                    'zip' => $user->zip,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->affected();
            });
    }

    public function down(): void
    {
        // Copy data back to users table
        DB::table('addresses')
            ->join('users', 'users.id', '=', 'addresses.user_id')
            ->cursor()
            ->each(function ($address) {
                DB::table('users')
                    ->where('id', $address->user_id)
                    ->update([
                        'address_line1' => $address->line1,
                        'address_line2' => $address->line2,
                        'city' => $address->city,
                        'state' => $address->state,
                        'zip' => $address->zip,
                    ]);
            });

        DB::table('addresses')->truncate();
    }
};
```

---

## Architecture

This package follows SOLID principles and uses clean architecture:

### Core Interfaces

| Interface | Description |
|-----------|-------------|
| `MigrationInterface` | Contract for data migrations |
| `MigratorInterface` | Contract for the migration runner |
| `MigrationRepositoryInterface` | Contract for migration state persistence |
| `MigrationFileResolverInterface` | Contract for locating and resolving migration files |
| `BackupServiceInterface` | Contract for backup services |

### Key Components

```
src/
├── Commands/                    # Artisan commands
│   ├── DataMigrateCommand.php
│   ├── DataMigrateFreshCommand.php
│   ├── DataMigrateRollbackCommand.php
│   ├── DataMigrateStatusCommand.php
│   └── MakeDataMigrationCommand.php
├── Concerns/
│   └── TracksProgress.php       # Progress bar trait
├── Contracts/                   # Interfaces
├── DTO/
│   └── MigrationRecord.php      # Typed data transfer object
├── Exceptions/
│   ├── MigrationException.php
│   ├── MigrationNotFoundException.php
│   └── TimeoutException.php
├── Facades/
│   └── DataMigrations.php
├── Migration/
│   ├── DataMigration.php        # Base migration class
│   ├── MigrationFileResolver.php
│   ├── MigrationRepository.php
│   └── Migrator.php
├── Services/
│   ├── NullBackupService.php
│   └── SpatieBackupService.php
└── DataMigrationsServiceProvider.php
```

### Using the Facade

```php
use Vherbaut\DataMigrations\Facades\DataMigrations;

// Get pending migrations
$pending = DataMigrations::getPendingMigrations();

// Run migrations programmatically
$ran = DataMigrations::run(['dry-run' => false]);

// Rollback
$rolledBack = DataMigrations::rollback(['step' => 1]);

// Get repository
$repo = DataMigrations::getRepository();
```

---

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer phpstan
```

### Testing Your Migrations

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class DataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_splits_user_names(): void
    {
        // Arrange
        DB::table('users')->insert([
            'full_name' => 'John Doe',
            'first_name' => null,
            'last_name' => null,
        ]);

        // Act
        $this->artisan('data:migrate', ['--force' => true])
            ->assertSuccessful();

        // Assert
        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }
}
```

---

## Best Practices

### General Guidelines

1. **Always test in staging first** — Use `--dry-run` to preview changes before executing
2. **Keep migrations focused** — One logical change per migration
3. **Document with `$description`** — Future you will thank you
4. **Set `$affectedTables`** — Enables auto-backup and documentation

### For Large Datasets

1. **Disable transactions** — Set `$withinTransaction = false` to prevent lock timeouts
2. **Use chunks** — Process records in batches to avoid memory exhaustion
3. **Implement `getEstimatedRows()`** — Enables progress tracking and confirmation prompts
4. **Use `chunkLazy()`** — More memory-efficient than `chunk()` for very large datasets

### For Safety

1. **Make migrations idempotent** — Safe to re-run if interrupted
2. **Implement `down()` when possible** — Enables rollback
3. **Use row counting** — Call `$this->affected()` for accurate logging
4. **Enable auto-backup** — For critical data transformations

### For Debugging

1. **Use `$this->log()`** — Logs to file and console
2. **Run with `-v` flag** — See stack traces on errors
3. **Check `data:status`** — View migration history and failures

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure tests pass (`composer test`)
5. Ensure PHPStan passes (`composer phpstan`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

---

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

## Security

If you discover a security vulnerability, please email vincenth.lzh@gmail.com instead of using the issue tracker.

---

## Credits

- [Vincent Herbaut](https://github.com/vherbaut)
- [All Contributors](../../contributors)

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
