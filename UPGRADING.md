# Upgrading

## From 1.x to 2.0.0

2.0.0 is a major release. Read this guide before running `composer update`: each section names a breaking change, who is affected and what to do. Sections are ordered the way you will meet them: dependencies first, then the tracking table, then your migration files, then custom integrations.

### Requirements

- PHP 8.2 or higher (unchanged).
- Laravel 12 or 13. Laravel 10 and 11 reached end of life, and Composer 2.9 or later refuses to install them because of their published security advisories.

Change the constraint in your `composer.json` to `"vherbaut/laravel-data-migrations": "^2.0"` and run `composer update`.

### Timeout removed

The execution timeout never bounded a migration: it relied on `set_time_limit()`, which does not count the time spent in database queries. The feature is gone.

- Remove the `protected ?int $timeout` property from your migrations.
- Remove the `timeout` key from `config/data-migrations.php` if you published the config file.
- Custom `MigrationInterface` implementations drop `getTimeout()`.
- `Vherbaut\DataMigrations\Exceptions\TimeoutException` no longer exists.

Rely on the timeout of your deployment pipeline or of your database instead.

### Reversible migrations

`data:rollback` used reflection to guess whether a migration declared `down()`. A migration that can be reverted now says so by implementing `Vherbaut\DataMigrations\Contracts\Reversible`, the interface that carries `down()`.

Before:

```php
return new class extends DataMigration
{
    public function up(): void { /* ... */ }

    public function down(): void { /* ... */ }
};
```

After:

```php
use Vherbaut\DataMigrations\Contracts\Reversible;
use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration implements Reversible
{
    public function up(): void { /* ... */ }

    public function down(): void { /* ... */ }
};
```

What changes for you:

- Add `implements Reversible` to every migration that declares `down()`, including abstract base classes of your own. Until you do, `data:rollback` skips the migration and prints `Skipping (declares down() but does not implement Reversible)`; the record stays `completed`.
- `MigrationInterface::down()`, `MigrationInterface::isReversible()` and the empty `DataMigration::down()` no longer exist. Replace `$migration->isReversible()` with `$migration instanceof Reversible`. Custom `MigrationInterface` implementations drop both methods and implement `Reversible` when they support rollback.
- `dryRun()['reversible']` and the `Reversible:` line of `data:migrate --dry-run` follow the interface.

### Migration file resolution

`MigrationFileResolver::resolve()` used to include a file twice (`require_once`, then `require` when the source contained `return new class`) and guessed the class name from the file name without checking where that class came from. It now includes a file once and resolves it the way Laravel resolves schema migrations:

1. A missing file throws `Vherbaut\DataMigrations\Exceptions\MigrationNotFoundException`.
2. When a class named after the file (`2024_01_01_000000_update_user_emails.php` gives `UpdateUserEmails`) exists and was declared in that very file, it is instantiated. An application class that merely shares the name is ignored.
3. Otherwise the file is included once; it must return the migration instance (anonymous class) or declare the named class.
4. Anything else throws `Vherbaut\DataMigrations\Exceptions\InvalidMigrationException`.

Both exceptions extend `MigrationException`, which `data:migrate` and `data:rollback` already report with exit code 1. Migration files written from the stubs need no change. A migration file that declared a named class under a different name than the one derived from the file name now throws `InvalidMigrationException`: rename the class or return an instance from the file.

### Chunk helpers and `chunk_size`

`chunk()`, `chunkLazy()` and `chunkUpdate()` now add the rows they process to the affected row count (`getRowsAffected()`, the `rows_affected` column and the `DataMigrationEnded` event). The 1.x chunked stub compensated with `$this->affected($processed)` after `chunk()`: remove that line from migrations generated with it, or the rows are counted twice.

Before:

```php
$processed = $this->chunk('users', fn ($user) => $this->process($user));

$this->affected($processed);
```

After:

```php
$processed = $this->chunk('users', fn ($user) => $this->process($user));
```

The `chunk_size` config key is removed: no code ever read it, and `$chunkSize` keeps its `int` type so that migrations declaring `protected int $chunkSize = 500;` keep working. Set the chunk size on the migration, or pass it to the helper.

### Tracking table schema

The `status` column becomes a plain string, `rows_affected` and `duration_ms` become unsigned big integers and a `status` index is added. Publish and run the upgrade migration.

### Retry and unresolved migrations

A migration recorded as `failed`, or still `running` after a crash, blocks `data:migrate` until `--retry-failed` is passed, unless the migration declares `$idempotent = true`. A `rolled_back` migration runs again on the next `data:migrate`. A `running` record can no longer be rolled back.

### `data:fresh` renamed `data:refresh`

`data:refresh` rolls back every completed migration that implements `Reversible`, then runs the pending migrations. It no longer deletes the tracking records.

### Interfaces and output

`MigrationInterface::setOutput()` and `MigratorInterface::setOutput()` receive a `Vherbaut\DataMigrations\Contracts\MigrationOutput` instead of `Illuminate\Console\OutputStyle`. `MigratorInterface::getNotes()` and the `dry-run` option of `run()` are gone. `MigrationRepositoryInterface` gains `getUnresolved()` and loses `setConnection()`. The `data:status` row object `MigrationStatus` is renamed `MigrationStatusEntry`; the `MigrationStatus` name now belongs to the status enum.

### Backup

`BackupServiceInterface::backup(string $migrationName): void` replaces `backupTables()` and `isAvailable()`. A failed backup throws `BackupFailedException` and the migration does not start.

### Testing helpers

`InteractsWithDataMigrations::dataMigrationStatus()` returns the `MigrationStatus` enum. `MigratorFake` follows the `MigratorInterface` changes above.

### Coming from 1.1.x

If you skipped 1.2.0, also review its interface additions: `MigratorInterface::getOrphanedMigrations()`, `MigrationFileResolverInterface::setPaths()` and `getPaths()`, `MigrationRepositoryInterface::getRollbackable()` and `getRollbackableByBatch()` (1.1.2), and the trailing `?string $column = null` parameter of the protected `chunk()`, `chunkLazy()` and `chunkUpdate()` helpers.
