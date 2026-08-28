# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [Unreleased]

### Added

- Events `DataMigrationStarted`, `DataMigrationEnded`, `DataMigrationFailed` and `NoPendingDataMigrations`, dispatched by the migrator around `up()` and `down()` (never during a dry run). `Migrator` accepts an optional event dispatcher as fifth constructor argument
- `--isolated[=CODE]` option on `data:migrate`, `data:rollback` and `data:fresh`, backed by a single cache lock shared by the three commands, with a new `lock` config block (`enabled`, `store`, `ttl`)
- `data:status --json`, printing the status list as a JSON array. `data:status` also lists the tracking records whose migration file no longer exists, flagged `(orphaned)` and counted in the summary line
- `data:prune` command deleting those orphaned records. It refuses to run when the migrations directory contains no file at all while records exist
- `MigratorInterface::getOrphanedMigrations()`. Custom migrator implementations must implement it
- `$chunkColumn` property (default `'id'`) and optional trailing `$column` parameter on `chunk()`, `chunkLazy()` and `chunkUpdate()` to paginate by another key
- `DataMigrations::fake()`, returning a `MigratorFake` that records runs and rollbacks without executing them, with `assertRan()`, `assertNotRan()`, `assertNothingRan()`, `assertRolledBack()` and `assertNothingRolledBack()`
- `Vherbaut\DataMigrations\Testing\InteractsWithDataMigrations` trait for application test suites: `runDataMigration()`, `runDataMigrations()`, `rollbackDataMigration()` and the `assertDataMigration*()` assertions. `runDataMigration()` throws the previously unused `MigrationNotFoundException`
- `--path=*` and `--realpath` options on `data:migrate` and `data:rollback`, and `--path`/`--realpath` on `make:data-migration`, mirroring `artisan migrate`
- `MigrationFileResolverInterface::setPaths()` and `getPaths()`. Custom resolver implementations must implement them
- `run_after_migrate` config option chaining `data:migrate --force` after `migrate`, `migrate:fresh` and `migrate:refresh` through the `CommandFinished` console event. Inactive under `--pretend`, after a failed command and in the `testing` environment, where Laravel does not dispatch console events
- Tests covering `Migrator` (transaction modes, timeout, backup, dry run), the `MigrationRepository` logging methods and the `chunk*()` helpers
- `.gitattributes` with `export-ignore` entries so that tests, CI and tooling files are left out of the distribution archive
- `suggest` entry for `spatie/laravel-backup`, required by the `safety.auto_backup` option

### Changed

- `chunk()` and `chunkLazy()` paginate by key (`chunkById()` and `lazyById()`) instead of by offset, and `chunkUpdate()` walks the table by key range instead of issuing `UPDATE ... LIMIT`, which some drivers do not support. A subclass overriding one of these protected helpers must add the trailing `?string $column = null` parameter
- The `data:status` summary line ends with an `Orphaned:` count
- Rollback target selection (batch, step or last batch) lives in `RollbackTargetSelector`, shared by `Migrator` and `MigratorFake`
- `MigrationFileResolver::getMigrationFiles()` sorts by file name across every configured directory (same order as before for a single directory)

### Fixed

- `chunkUpdate()` looped forever when the update did not remove the rows from the predicate
- `chunk()` could skip rows when the callback moved them out of the result set, a side effect of offset pagination
- The PHPStan baseline is gone: `DataMigrateStatusCommand` builds typed `MigrationStatus` objects instead of array shapes


## [1.1.2] - 2026-08-28

### Fixed

- `data:rollback --step=N` and `data:rollback --batch=N` only target `completed` or `running` migrations. They previously ran `down()` again on migrations already `rolled_back` or `failed` (the 1.0.3 fix only covered the default rollback)
- `data:fresh` returns the exit code of the underlying `data:migrate` run instead of always reporting success
- `data:migrate --step` assigns a separate batch number to each migration. The option was accepted but ignored
- The package no longer loads its own tracking table migration when a published copy (`*_create_data_migrations_table.php`) exists in `database/migrations`. A renamed published copy previously caused a "table already exists" error on `php artisan migrate`
- The data migrations directory is only created when running in the console, no longer on every HTTP request

### Changed

- Generated migrations no longer declare an empty `down()` method. An empty `down()` made every migration look reversible, so `data:rollback` marked it as `rolled_back` without reverting anything. **Remove empty `down()` methods from previously generated migrations** (and from published stubs in `stubs/`) so that `data:rollback` skips them
- CI covers Laravel 13 and PHP 8.5, and runs on the `develop` branch

### Added

- `MigrationRepositoryInterface::getRollbackable()` and `getRollbackableByBatch()`. Custom repository implementations must implement them
- Documentation for the `data-migrations-migrations` publish tag and for the retry behaviour introduced in 1.0.3

### Removed

- `data:fresh --seed` option, which was reserved and never implemented


## [1.1.1] - 2026-03-26

### Fixed

- Code style compatibility with Laravel Pint 1.29


## [1.1.0] - 2026-03-25

### Added

- Laravel 13 support


## [1.0.3] - 2025-12-29

### Added

- Retry failed migrations: migrations with `failed` status can now be automatically retried with `data:migrate`
- Retry rolled back migrations: migrations with `rolled_back` status can be replayed with `data:migrate`
- Support for migration names with spaces: `make:data-migration "split user names"`

### Fixed

- Consecutive rollbacks now work correctly: `getLast()` filters by `completed`/`running` status, so after rolling back batch 2, a new rollback will correctly target batch 1
- Misleading rollback message: the "X migration(s) rolled back" counter no longer includes skipped migrations (not reversible)

### Changed

- `MigrationRepository::logStart()` now deletes `failed` or `rolled_back` records before inserting a new one
- `MigrationRepository::getLast()` filters by status to only return rollbackable migrations
- `Migrator::rollbackMigration()` now returns `bool` instead of `void`


## [1.0.2] - 2025-12-29

### Fixed

- Composer package metadata


## [1.0.1] - 2025-12-28

### Changed

- Documentation and Composer metadata updates


## [1.0.0] - 2025-12-28

### Added

- Initial release of Laravel Data Migrations
- `make:data-migration` command to create new data migrations
- `data:migrate` command to run pending data migrations
- `data:rollback` command to rollback migrations
- `data:status` command to view migration status
- `data:fresh` command to reset and re-run all migrations
- Support for dry-run mode (`--dry-run`)
- Chunked processing for large datasets
- Progress bar tracking for long-running operations
- Transaction support with configurable modes (`auto`, `always`, `never`)
- Production safety with `--force` flag requirement
- Row count confirmation threshold
- Auto-backup integration with spatie/laravel-backup
- Configurable execution timeout
- Idempotent migration support
- Reversible migration support with `down()` method
- Custom database connection support
- Comprehensive logging
- PHPStan Level 5 compliance
- Full test suite with Pest

### Architecture

- SOLID principles implementation
- Interface-based design (`MigrationInterface`, `MigratorInterface`, etc.)
- Dependency injection throughout
- Clean separation of concerns
- Typed DTOs (`MigrationRecord`)

[Unreleased]: https://github.com/vherbaut/laravel-data-migrations/compare/1.1.2...HEAD
[1.1.2]: https://github.com/vherbaut/laravel-data-migrations/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/vherbaut/laravel-data-migrations/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/vherbaut/laravel-data-migrations/compare/1.0.3...1.1.0
[1.0.3]: https://github.com/vherbaut/laravel-data-migrations/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/vherbaut/laravel-data-migrations/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/vherbaut/laravel-data-migrations/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/vherbaut/laravel-data-migrations/releases/tag/1.0.0
