# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


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


## [1.0.0] - 2024-12-28

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

[Unreleased]: https://github.com/vherbaut/laravel-data-migrations/compare/v1.0.3...HEAD
[1.0.3]: https://github.com/vherbaut/laravel-data-migrations/compare/v1.0.0...v1.0.3
[1.0.0]: https://github.com/vherbaut/laravel-data-migrations/releases/tag/v1.0.0
