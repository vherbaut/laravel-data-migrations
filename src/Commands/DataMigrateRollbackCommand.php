<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Throwable;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Exceptions\MigrationException;

/**
 * Command to rollback data migrations.
 */
class DataMigrateRollbackCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:rollback
                            {--step=0 : Number of migrations to rollback}
                            {--batch= : Rollback a specific batch number}
                            {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback the last data migration batch';

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

        $this->migrator->setOutput($this->output);

        if (! $this->migrator->getRepository()->repositoryExists()) {
            $this->error('Data migrations table not found.');

            return self::FAILURE;
        }

        $options = [
            'step' => (int) $this->option('step'),
        ];

        // Handle batch option - pass it to the migrator
        $batchOption = $this->option('batch');

        if ($batchOption !== null) {
            $batch = (int) $batchOption;
            $migrations = $this->migrator->getRepository()->getMigrationsByBatch($batch);

            if ($migrations->isEmpty()) {
                $this->warn("No migrations found for batch {$batch}.");

                return self::SUCCESS;
            }

            $this->info("Rolling back batch {$batch}...");
            $options['batch'] = $batch;
        }

        try {
            $rolledBack = $this->migrator->rollback($options);

            if (count($rolledBack) === 0) {
                return self::SUCCESS;
            }

            $this->newLine();
            $this->info(count($rolledBack).' migration(s) rolled back.');

            return self::SUCCESS;
        } catch (MigrationException $e) {
            $this->error("Rollback error: {$e->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Unexpected error: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
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
                'You are about to rollback data migrations in production. This may cause data loss. Continue?'
            );
        }

        return true;
    }
}
