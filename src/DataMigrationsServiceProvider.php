<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Vherbaut\DataMigrations\Commands\DataMigrateCommand;
use Vherbaut\DataMigrations\Commands\DataMigrateFreshCommand;
use Vherbaut\DataMigrations\Commands\DataMigrateRollbackCommand;
use Vherbaut\DataMigrations\Commands\DataMigrateStatusCommand;
use Vherbaut\DataMigrations\Commands\MakeDataMigrationCommand;
use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Migration\MigrationFileResolver;
use Vherbaut\DataMigrations\Migration\MigrationRepository;
use Vherbaut\DataMigrations\Migration\Migrator;
use Vherbaut\DataMigrations\Services\NullBackupService;
use Vherbaut\DataMigrations\Services\SpatieBackupService;

/**
 * Service provider for Laravel Data Migrations.
 */
class DataMigrationsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/data-migrations.php', 'data-migrations');

        $this->registerRepository();
        $this->registerFileResolver();
        $this->registerBackupService();
        $this->registerMigrator();
    }

    /**
     * Register the migration repository.
     *
     * @return void
     */
    protected function registerRepository(): void
    {
        $this->app->singleton(MigrationRepositoryInterface::class, function (Application $app): MigrationRepository {
            /** @var string $table */
            $table = config('data-migrations.table');

            return new MigrationRepository(
                $app['db'],
                $table
            );
        });

        $this->app->alias(MigrationRepositoryInterface::class, MigrationRepository::class);
    }

    /**
     * Register the migration file resolver.
     *
     * @return void
     */
    protected function registerFileResolver(): void
    {
        $this->app->singleton(MigrationFileResolverInterface::class, function (Application $app): MigrationFileResolver {
            /** @var string $path */
            $path = config('data-migrations.path');

            return new MigrationFileResolver(
                $app['files'],
                $path
            );
        });

        $this->app->alias(MigrationFileResolverInterface::class, MigrationFileResolver::class);
    }

    /**
     * Register the backup service.
     *
     * @return void
     */
    protected function registerBackupService(): void
    {
        $this->app->singleton(BackupServiceInterface::class, function (): BackupServiceInterface {
            // Use SpatieBackupService if available, otherwise NullBackupService
            if (class_exists(\Spatie\Backup\BackupServiceProvider::class)) {
                return new SpatieBackupService;
            }

            return new NullBackupService;
        });
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerMigrator(): void
    {
        $this->app->singleton(MigratorInterface::class, function (Application $app): Migrator {
            return new Migrator(
                $app->make(MigrationRepositoryInterface::class),
                $app['db'],
                $app->make(MigrationFileResolverInterface::class),
                $app->make(BackupServiceInterface::class)
            );
        });

        $this->app->alias(MigratorInterface::class, Migrator::class);
        $this->app->alias(MigratorInterface::class, 'data-migrator');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerCommands();
        $this->registerMigrations();
        $this->ensureMigrationPathExists();
    }

    /**
     * Register publishable resources.
     *
     * @return void
     */
    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/data-migrations.php' => config_path('data-migrations.php'),
            ], 'data-migrations-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'data-migrations-migrations');

            $this->publishes([
                __DIR__.'/../stubs/' => base_path('stubs'),
            ], 'data-migrations-stubs');
        }
    }

    /**
     * Register console commands.
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeDataMigrationCommand::class,
                DataMigrateCommand::class,
                DataMigrateRollbackCommand::class,
                DataMigrateStatusCommand::class,
                DataMigrateFreshCommand::class,
            ]);
        }
    }

    /**
     * Register package migrations.
     *
     * @return void
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Ensure the data migrations directory exists.
     *
     * @return void
     */
    protected function ensureMigrationPathExists(): void
    {
        /** @var string $path */
        $path = config('data-migrations.path');

        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }
}
