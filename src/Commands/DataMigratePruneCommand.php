<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;

/**
 * Command to delete tracking records whose migration file no longer exists.
 */
class DataMigratePruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:prune
                            {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove tracking records whose migration file no longer exists';

    /**
     * The migrator instance.
     *
     * @var MigratorInterface
     */
    protected MigratorInterface $migrator;

    /**
     * Create a new command instance.
     *
     * @param MigratorInterface $migrator
     */
    public function __construct(MigratorInterface $migrator)
    {
        parent::__construct();
        $this->migrator = $migrator;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $repository = $this->migrator->getRepository();

        if (! $repository->repositoryExists()) {
            $this->error('Data migrations table not found. Run: php artisan migrate');

            return self::FAILURE;
        }

        if (count($this->migrator->getMigrationFiles()) === 0) {
            if ($repository->getMigrations()->isNotEmpty()) {
                /** @var string $path */
                $path = config('data-migrations.path');

                $this->error("No data migration files found in {$path}. Refusing to prune every record.");

                return self::FAILURE;
            }
        }

        $orphaned = $this->migrator->getOrphanedMigrations();

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned records found.');

            return self::SUCCESS;
        }

        foreach ($orphaned as $record) {
            $this->line("<comment>Pruned:</comment> {$record->migration}");
            $repository->delete($record->migration);
        }

        $this->newLine();
        $this->info("{$orphaned->count()} orphaned record(s) pruned.");

        return self::SUCCESS;
    }

    /**
     * Determine if the command should proceed.
     *
     * @return bool
     */
    protected function confirmToProceed(): bool
    {
        /** @var bool $shouldConfirm */
        $shouldConfirm = config('data-migrations.safety.require_force_in_production', true);

        if ($shouldConfirm && app()->environment('production')) {
            return (bool) $this->option('force') || $this->confirm(
                'You are about to delete orphaned data migration records in production. Continue?'
            );
        }

        return true;
    }
}
