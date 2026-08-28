<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Facades\DataMigrations;

function createMigrationInDirectory(string $directory, string $name, string $content): string
{
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $file = $directory.'/'.date('Y_m_d_His').'_'.$name.'.php';
    file_put_contents($file, $content);

    return $file;
}

function removeMigrationDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (glob($directory.'/*.php') ?: [] as $file) {
        unlink($file);
    }

    rmdir($directory);
}

beforeEach(function (): void {
    $this->artisan('migrate');
    $this->altPath = __DIR__.'/../fixtures/alt';
    $this->relativePath = 'data-migrations-relative';
});

afterEach(function (): void {
    removeMigrationDirectory($this->altPath);
    removeMigrationDirectory(base_path($this->relativePath));
});

it('runs only the migrations found in an absolute --path with --realpath', function (): void {
    $this->createTestMigration('default_pending', dataMigrationContent());
    $alt = createMigrationInDirectory($this->altPath, 'alt_migration', dataMigrationContent());

    $this->artisan('data:migrate', ['--force' => true, '--path' => [$this->altPath], '--realpath' => true])
        ->assertSuccessful();

    expect(DB::table('data_migrations')->pluck('migration')->all())->toBe([basename($alt, '.php')]);
});

it('resolves a relative --path from the base path', function (): void {
    $file = createMigrationInDirectory(base_path($this->relativePath), 'relative_migration', dataMigrationContent());

    $this->artisan('data:migrate', ['--force' => true, '--path' => [$this->relativePath]])
        ->assertSuccessful();

    expect(DB::table('data_migrations')->pluck('migration')->all())->toBe([basename($file, '.php')]);
});

it('restores the default path after the command', function (): void {
    $default = $this->createTestMigration('default_pending', dataMigrationContent());
    createMigrationInDirectory($this->altPath, 'alt_migration', dataMigrationContent());

    $this->artisan('data:migrate', ['--force' => true, '--path' => [$this->altPath], '--realpath' => true])
        ->assertSuccessful();

    expect(app(MigrationFileResolverInterface::class)->getPaths())->toBe([config('data-migrations.path')])
        ->and(DataMigrations::getPendingMigrations())->toBe([$default]);
});

it('rolls back only migrations found in --path', function (): void {
    $alt = createMigrationInDirectory($this->altPath, 'alt_reversible', dataMigrationContent('', '', '$this->affected(1);'));
    $this->artisan('data:migrate', ['--force' => true, '--path' => [$this->altPath], '--realpath' => true])
        ->assertSuccessful();

    $this->artisan('data:rollback', ['--force' => true])->assertSuccessful();

    expect(DB::table('data_migrations')->value('status'))->toBe('completed');

    $this->artisan('data:rollback', ['--force' => true, '--path' => [$this->altPath], '--realpath' => true])
        ->assertSuccessful();

    expect(DB::table('data_migrations')->where('migration', basename($alt, '.php'))->value('status'))->toBe('rolled_back');
});

it('creates a migration in an absolute --path with --realpath', function (): void {
    $this->artisan('make:data-migration', ['name' => 'in_alt_dir', '--path' => $this->altPath, '--realpath' => true])
        ->assertSuccessful();

    expect(glob($this->altPath.'/*_in_alt_dir.php'))->toHaveCount(1);
});

it('creates a migration in a relative --path from the base path', function (): void {
    $this->artisan('make:data-migration', ['name' => 'in_relative_dir', '--path' => $this->relativePath])
        ->assertSuccessful();

    expect(glob(base_path($this->relativePath).'/*_in_relative_dir.php'))->toHaveCount(1);
});
