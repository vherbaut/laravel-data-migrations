# Contributing to Laravel Data Migrations

Thank you for considering contributing to Laravel Data Migrations! This document provides guidelines and instructions for contributing.

## Code of Conduct

Please be respectful and constructive in all interactions. We are committed to providing a welcoming and inclusive environment for everyone.

## How to Contribute

### Reporting Bugs

Before submitting a bug report:

1. Check if the issue has already been reported
2. Make sure you are using the latest version
3. Collect information about the bug (PHP version, Laravel version, steps to reproduce)

When reporting a bug, please include:

- A clear and descriptive title
- Steps to reproduce the behavior
- Expected behavior
- Actual behavior
- PHP and Laravel versions
- Any relevant code snippets or error messages

### Suggesting Features

Feature suggestions are welcome! Please:

1. Check if the feature has already been suggested
2. Provide a clear use case for the feature
3. Explain how it would benefit other users

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Install dependencies**: `composer install`
3. **Make your changes** following our coding standards
4. **Add tests** for any new functionality
5. **Run tests**: `composer test`
6. **Run PHPStan**: `composer phpstan`
7. **Commit your changes** with a descriptive message
8. **Push to your fork** and submit a pull request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/laravel-data-migrations.git
cd laravel-data-migrations

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan
```

## Coding Standards

### PHP Standards

- Follow PSR-1, PSR-2, and PSR-12
- Use PHP 8.1+ features where appropriate
- Always use strict types: `declare(strict_types=1);`
- Use typed properties and return types
- Document with PHPDoc when types aren't sufficient

### Laravel Conventions

- Follow Laravel naming conventions
- Use dependency injection over facades in classes
- Keep controllers thin, services fat

### Code Quality

- **PHPStan Level 5**: All code must pass PHPStan analysis at level 5
- **Tests Required**: New features must include tests
- **Documentation**: Update README.md for user-facing changes

### Commit Messages

- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Keep the first line under 72 characters
- Reference issues and pull requests when relevant

Examples:
```
Add dry-run support to data:migrate command

Implement backup service integration

Fix transaction handling for large datasets

Refs #123
```

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/pest tests/Feature/DataMigrateCommandTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

### Writing Tests

- Use Pest for all new tests
- Follow the existing test structure
- Test both success and failure cases
- Use descriptive test names

```php
it('runs pending migrations successfully', function (): void {
    // Arrange
    $this->createTestMigration('test_migration', '...');

    // Act
    $this->artisan('data:migrate', ['--force' => true])
        ->assertSuccessful();

    // Assert
    $this->assertDatabaseHas('data_migrations', [
        'status' => 'completed',
    ]);
});
```

## Static Analysis

All code must pass PHPStan at level 5:

```bash
composer phpstan
```

If you need to add type annotations or ignores, document why.

## Documentation

- Update README.md for new features
- Add PHPDoc blocks to public methods
- Include code examples where helpful

## Questions?

If you have questions, feel free to:

1. Open an issue for discussion
2. Start a discussion in the repository

Thank you for contributing!
