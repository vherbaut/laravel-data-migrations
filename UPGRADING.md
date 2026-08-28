# Upgrading

## From 1.x to 2.0.0

2.0.0 is a major release. Read this guide before running `composer update`: each section names a breaking change, who is affected and what to do. Sections are ordered the way you will meet them: dependencies first, then the tracking table, then your migration files and commands, then custom integrations.

### Requirements

- PHP 8.2 or higher (unchanged).
- Laravel 12 or 13. Laravel 10 and 11 reached end of life, and Composer 2.9 or later refuses to install them because of their published security advisories.

Change the constraint in your `composer.json` to `"vherbaut/laravel-data-migrations": "^2.0"` and run `composer update`.

### Tracking table schema

The tracking table changes shape:

| Column | 1.x | 2.0 |
|---|---|---|
| `status` | `enum('pending', 'running', 'completed', 'failed', 'rolled_back')`, default `'pending'` | `string(20)`, no default |
| `rows_affected`, `duration_ms` | `unsignedInteger` | `unsignedBigInteger` |
| Indexes | `unique(migration)`, `index(batch, status)` | the same, plus `index(status)` |

New installs get this shape from the package migration. Existing installs run the upgrade migration shipped in `database/upgrades/`, which is never loaded automatically:

```bash
php artisan vendor:publish --tag=data-migrations-upgrade
php artisan migrate
```

The migration reads `data-migrations.table` and `data-migrations.connection`, converts the columns in place, adds the missing index and keeps every row. It is safe to run again and does nothing on a table already in the 2.0 shape, so the published copy can stay in `database/migrations`. Back up the tracking table first: it is small, and `migrate:rollback` does not restore the 1.x shape.

Driver notes:

- MySQL and MariaDB: one `ALTER TABLE ... MODIFY` per column; the enum labels become their string values. Nothing is transactional there, so run `migrate` again if the process is interrupted.
- PostgreSQL: the CHECK constraint created for the enum is looked up in `pg_constraint` and dropped before the type change. Verify afterwards with `\d data_migrations` that no `_check` constraint remains on `status`.
- SQLite: `change()` rebuilds the table, which drops the constraint and the default by itself.
- SQL Server: the CHECK constraint is looked up in `sys.check_constraints`, and the `(batch, status)` index is dropped then recreated because SQL Server refuses to shrink an indexed `nvarchar` column (error 5074). If `migrate` fails with error 5074 or 3701, drop that index and any CHECK constraint on `status` yourself (`select name from sys.check_constraints where parent_object_id = object_id('data_migrations')`), then run `migrate` again.

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

### Retry and unresolved migrations

In 1.x, a migration recorded as `failed` was silently run again by the next `data:migrate`, and a record left as `running` by a killed process counted as completed and could even be rolled back. In 2.0 both statuses are unresolved:

- `data:migrate` refuses to run while an unresolved migration still has a file: it lists their names, advises `--retry-failed` and exits with code 1 (`UnresolvedMigrationsException`, a `MigrationException`). Nothing runs, not even the pending migrations.
- `data:migrate --retry-failed` replaces their record and runs them again, in file order together with the pending migrations. Make sure no other process is still running them first.
- A migration declaring `protected bool $idempotent = true;` is retried without the option, since running it twice is safe by definition. The property was documented but never read in 1.x.
- `data:rollback` ignores `running` records.
- A `rolled_back` migration is pending again and runs on the next `data:migrate`, as before.
- A record whose file no longer exists never blocks a run: `data:status` lists it as orphaned and `data:prune` deletes it.

`MigratorInterface::run()` accepts the `retry-failed` option, `getPendingMigrations()` no longer lists the non idempotent unresolved migrations, and `MigratorFake::run()` records the unresolved files too when the option is passed.

### `data:fresh` renamed `data:refresh`

`data:fresh` deleted every tracking record and ran every `up()` again on data that had already been transformed. It is replaced by `data:refresh`, modelled on `migrate:refresh`: every completed migration implementing `Reversible` is rolled back, most recent first, then the pending migrations run. Migrations without `Reversible` are skipped and keep their record. Unresolved migrations make the command fail: resolve them with `data:migrate --retry-failed` first.

Update the scripts calling `data:fresh`. `data:refresh` keeps `--force` and `--isolated`. The rollback and the run happen in one process, so `DataMigrationsCommandMutex` no longer counts nested commands and is no longer a singleton.

To forget every record and run everything again, which is what `data:fresh` did, truncate the tracking table yourself and run `data:migrate`.

### Status enum, records and repository

`Vherbaut\DataMigrations\Enums\MigrationStatus` (`Pending`, `Running`, `Completed`, `Failed`, `RolledBack`) replaces the status strings:

- `MigrationRecord::$status` is a `MigrationStatus`. The `isCompleted()` family is unchanged; compare with `$record->status === MigrationStatus::Completed` or read `$record->status->value`.
- The `data:status` row object `Vherbaut\DataMigrations\DTO\MigrationStatus` is renamed `MigrationStatusEntry` and its `$status` property is the enum. The JSON printed by `data:status --json` is unchanged.
- `MigrationRepositoryInterface` gains `getUnresolved()` and loses `setConnection()`: the connection is the third constructor argument of `MigrationRepository`, filled from the new `connection` config key (null = default connection). The package migrations use that connection too.
- `getRan()`, `hasRun()`, `getLast()`, `getRollbackable()` and `getRollbackableByBatch()` only consider `completed` records, and `logStart()` replaces any previous record of the migration.

### Interfaces and output

The domain no longer depends on the console. `MigrationInterface::setOutput()` and `MigratorInterface::setOutput()` receive a `Vherbaut\DataMigrations\Contracts\MigrationOutput` instead of `Illuminate\Console\OutputStyle`:

- `Vherbaut\DataMigrations\Output\ConsoleOutput` wraps an `OutputStyle` (what the Artisan commands pass), `MemoryOutput` keeps the messages and the progress for tests and programmatic runs, `NullOutput` discards everything. A migration without output stays silent.
- `MigratorInterface::getNotes()` is gone: read the messages from the `MemoryOutput` you passed to `setOutput()`.
- `MigratorInterface::run()` no longer accepts the `dry-run` option: `data:migrate --dry-run` describes the pending migrations itself and never calls `run()`. Custom code that relied on the option should call `getPendingMigrations()` and `resolve($file)->dryRun()`.
- `MigratorInterface::getBlockingMigrations()` is new and must be implemented by custom migrators.
- Inside a migration, `$this->info()`, `$this->warn()` and `$this->error()` print styled console lines rather than Symfony blocks; `TracksProgress` now requires an `output()` method, provided by `DataMigration`.
- The migrator prints plain messages (`Migrating: name`, `Migrated: name (12ms, 3 rows)`, `Skipping (not reversible): name`) without console markup; scripts grepping the output keep the same words.

### Backup

In 1.x, a failing backup only printed a warning and the migration ran anyway, and `backupTables()` ignored the `$tables` it received because spatie/laravel-backup only dumps whole databases. In 2.0:

- `BackupServiceInterface::backup(string $migrationName): void` replaces `backupTables()` and `isAvailable()`. It runs before the tracking record is written; when it throws `Vherbaut\DataMigrations\Exceptions\BackupFailedException` (a `MigrationException`) the migration does not start, no record is created and the command exits with code 1.
- The service provider binds `NullBackupService` when `safety.auto_backup` is off, `SpatieBackupService` when it is on and spatie/laravel-backup is installed, and `UnavailableBackupService` (which throws) when it is on without the package. Install the package or turn the option off.
- `$affectedTables` no longer drives the backup; keep it for documentation and the dry run.

Custom `BackupServiceInterface` implementations implement `backup()` and throw `BackupFailedException` on failure.

### Testing helpers

`InteractsWithDataMigrations::dataMigrationStatus()` returns a `MigrationStatus` (or null); the `assertDataMigration*()` helpers are unchanged. `MigratorFake::run(['retry-failed' => true])` records the unresolved migrations as ran, `MigratorFake::run(['dry-run' => true])` no longer means anything (use `data:migrate --dry-run`), and `MigratorFake` follows the `MigratorInterface` changes described above. To assert on the messages of a real run, pass a `MemoryOutput` to `DataMigrations::setOutput()`.

### Coming from 1.1.x

If you skipped 1.2.0, also review its interface additions: `MigratorInterface::getOrphanedMigrations()`, `MigrationFileResolverInterface::setPaths()` and `getPaths()`, `MigrationRepositoryInterface::getRollbackable()` and `getRollbackableByBatch()` (1.1.2), and the trailing `?string $column = null` parameter of the protected `chunk()`, `chunkLazy()` and `chunkUpdate()` helpers.
