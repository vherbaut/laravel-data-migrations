<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Vherbaut\DataMigrations\Migration\MigrationFileResolver;

beforeEach(function (): void {
    $this->filesystem = new Filesystem;
    $this->testPath = __DIR__.'/../fixtures/migrations';

    if (! is_dir($this->testPath)) {
        mkdir($this->testPath, 0755, true);
    }
});

afterEach(function (): void {
    $files = glob($this->testPath.'/*.php');

    if ($files !== false) {
        foreach ($files as $file) {
            unlink($file);
        }
    }
});

it('returns empty array when directory does not exist', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, '/nonexistent/path');

    expect($resolver->getMigrationFiles())->toBe([]);
});

it('gets migration files from directory', function (): void {
    file_put_contents($this->testPath.'/2024_01_01_000000_first_migration.php', '<?php return new class {};');
    file_put_contents($this->testPath.'/2024_01_02_000000_second_migration.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $files = $resolver->getMigrationFiles();

    expect($files)->toHaveCount(2);
});

it('sorts migration files by name', function (): void {
    file_put_contents($this->testPath.'/2024_01_02_000000_second_migration.php', '<?php return new class {};');
    file_put_contents($this->testPath.'/2024_01_01_000000_first_migration.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $files = $resolver->getMigrationFiles();

    expect(basename($files[0]))->toBe('2024_01_01_000000_first_migration.php')
        ->and(basename($files[1]))->toBe('2024_01_02_000000_second_migration.php');
});

it('extracts migration name from file path', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    $name = $resolver->getMigrationName('/path/to/2024_01_01_000000_test_migration.php');

    expect($name)->toBe('2024_01_01_000000_test_migration');
});

it('extracts migration class name from file path', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    $class = $resolver->getMigrationClass('/path/to/2024_01_01_000000_update_user_emails.php');

    expect($class)->toBe('UpdateUserEmails');
});

it('finds migration file by name', function (): void {
    file_put_contents($this->testPath.'/2024_01_01_000000_target_migration.php', '<?php return new class {};');
    file_put_contents($this->testPath.'/2024_01_02_000000_other_migration.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $file = $resolver->findMigrationFile('2024_01_01_000000_target_migration');

    expect($file)->toEndWith('2024_01_01_000000_target_migration.php');
});

it('returns null when migration file not found', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $file = $resolver->findMigrationFile('nonexistent_migration');

    expect($file)->toBeNull();
});
