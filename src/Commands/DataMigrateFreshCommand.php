<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;

/**
 * Command to reset and re-run all data migrations.
 */
class DataMigrateFreshCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:fresh
                            {--force : Force the operation to run in production}
                            {--seed : Seed the database after migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and re-run all data migrations';

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

        $this->info('Resetting data migration records...');

        $migrations = $repository->getMigrations();

        // Wrap deletion in a transaction for atomicity
        DB::transaction(function () use ($repository, $migrations): void {
            foreach ($migrations as $migration) {
                $repository->delete($migration->migration);
                $this->line("<comment>Reset:</comment> {$migration->migration}");
            }
        });

        $this->newLine();

        // Run all migrations
        $this->call('data:migrate', [
            '--force' => $this->option('force'),
        ]);

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
                'You are about to reset ALL data migration records in production. This is DANGEROUS. Continue?'
            );
        }

        return true;
    }
}
