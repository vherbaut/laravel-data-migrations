# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/vherbaut/laravel-data-migrations/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/vherbaut/laravel-data-migrations/releases/tag/v1.0.0
