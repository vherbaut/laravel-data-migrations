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

A migration that can be reverted now implements `Vherbaut\DataMigrations\Contracts\Reversible`, the interface that carries `down()`. `MigrationInterface::down()`, `MigrationInterface::isReversible()` and the empty `down()` of `DataMigration` are gone.

### Migration file resolution

`MigrationFileResolver::resolve()` includes a file once. A named migration class must be declared in its own file; a missing file throws `MigrationNotFoundException` and a file that does not produce a migration throws `InvalidMigrationException`.

### Chunk helpers and `chunk_size`

`chunk()`, `chunkLazy()` and `chunkUpdate()` count the rows they process through `affected()`. The `chunk_size` config key is honoured when a migration does not set `$chunkSize`.

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
