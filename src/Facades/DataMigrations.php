<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Facades;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Facade;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;

/**
 * Facade for the data migrations service.
 *
 * @method static array<int, string> run(array<string, mixed> $options = []) Run pending migrations
 * @method static void runMigration(string $file, int $batch, array<string, mixed> $options = []) Run a single migration
 * @method static array<int, string> rollback(array<string, mixed> $options = []) Rollback the last batch
 * @method static array<int, string> getPendingMigrations() Get pending migrations
 * @method static array<int, string> getMigrationFiles() Get all migration files
 * @method static MigrationInterface resolve(string $file) Resolve a migration instance
 * @method static string getMigrationName(string $file) Get migration name from file path
 * @method static array<int, string> getNotes() Get the notes
 * @method static static setOutput(OutputStyle $output) Set the output instance
 * @method static MigrationRepositoryInterface getRepository() Get the repository
 *
 * @see MigratorInterface
 */
class DataMigrations extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return MigratorInterface::class;
    }
}
