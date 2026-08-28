<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;
use Vherbaut\DataMigrations\DataMigrationsServiceProvider;
use Vherbaut\DataMigrations\Exceptions\BackupFailedException;
use Vherbaut\DataMigrations\Services\NullBackupService;
use Vherbaut\DataMigrations\Services\UnavailableBackupService;

beforeEach(function (): void {
    $this->databasePath = sys_get_temp_dir().'/data-migrations-'.uniqid();
    mkdir($this->databasePath.'/migrations', 0755, true);
    $this->app->useDatabasePath($this->databasePath);
});

afterEach(function (): void {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->databasePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($this->databasePath);
});

function makeServiceProviderSpy(Application $app): DataMigrationsServiceProvider
{
    return new class($app) extends DataMigrationsServiceProvider
    {
        /** @var array<int, string> */
        public array $loadedMigrationPaths = [];

        protected function loadMigrationsFrom($paths): void
        {
            $this->loadedMigrationPaths = array_merge($this->loadedMigrationPaths, (array) $paths);
        }

        public function exposedEnsureMigrationPathExists(): void
        {
            $this->ensureMigrationPathExists();
        }
    };
}

it('loads the package tracking migration when it has not been published', function (): void {
    $provider = makeServiceProviderSpy($this->app);

    $provider->boot();

    expect(array_map('realpath', $provider->loadedMigrationPaths))
        ->toBe([realpath(__DIR__.'/../../database/migrations')]);
});

it('skips the package tracking migration when a published copy exists', function (): void {
    touch($this->databasePath.'/migrations/2025_06_01_000000_create_data_migrations_table.php');
    $provider = makeServiceProviderSpy($this->app);

    $provider->boot();

    expect($provider->loadedMigrationPaths)->toBe([]);
});

it('creates the data migrations directory when running in console', function (): void {
    $path = $this->databasePath.'/data-migrations';
    config(['data-migrations.path' => $path]);

    makeServiceProviderSpy($this->app)->exposedEnsureMigrationPathExists();

    expect(is_dir($path))->toBeTrue();
});

it('does not create the data migrations directory outside the console', function (): void {
    $path = $this->databasePath.'/data-migrations';
    config(['data-migrations.path' => $path]);
    $app = Mockery::mock(Application::class)->makePartial();
    $app->shouldReceive('runningInConsole')->andReturnFalse();

    makeServiceProviderSpy($app)->exposedEnsureMigrationPathExists();

    expect(is_dir($path))->toBeFalse();
});

it('publishes the upgrade migration under its own tag without loading it', function (): void {
    $paths = ServiceProvider::pathsToPublish(DataMigrationsServiceProvider::class, 'data-migrations-upgrade');
    $provider = makeServiceProviderSpy($this->app);

    $provider->boot();

    expect(array_map('realpath', array_keys($paths)))->toBe([realpath(__DIR__.'/../../database/upgrades')])
        ->and(array_values($paths)[0])->toEndWith(DIRECTORY_SEPARATOR.'migrations')
        ->and(array_map('realpath', $provider->loadedMigrationPaths))->toBe([realpath(__DIR__.'/../../database/migrations')]);
});

it('keeps the upgrade migration out of the migrations publish tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(DataMigrationsServiceProvider::class, 'data-migrations-migrations');

    expect(array_map('realpath', array_keys($paths)))->toBe([realpath(__DIR__.'/../../database/migrations')]);
});

it('binds a null backup service when auto backup is off', function (): void {
    config(['data-migrations.safety.auto_backup' => false]);
    $this->app->forgetInstance(BackupServiceInterface::class);

    expect($this->app->make(BackupServiceInterface::class))->toBeInstanceOf(NullBackupService::class);
});

it('binds a backup service that refuses to run when auto backup is on without spatie/laravel-backup', function (): void {
    config(['data-migrations.safety.auto_backup' => true]);
    $this->app->forgetInstance(BackupServiceInterface::class);

    $service = $this->app->make(BackupServiceInterface::class);

    expect($service)->toBeInstanceOf(UnavailableBackupService::class)
        ->and(fn () => $service->backup('any'))->toThrow(BackupFailedException::class, 'spatie/laravel-backup');
});
