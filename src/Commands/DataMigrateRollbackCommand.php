<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;
use Vherbaut\DataMigrations\Commands\Concerns\ConfirmsProductionRun;
use Vherbaut\DataMigrations\Commands\Concerns\IsolatesDataMigrations;
use Vherbaut\DataMigrations\Commands\Concerns\ReportsMigrationFailures;
use Vherbaut\DataMigrations\Commands\Concerns\ResolvesMigrationPaths;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Output\ConsoleOutput;

/**
 * Command to rollback data migrations.
 */
class DataMigrateRollbackCommand extends Command implements Isolatable
{
    use ConfirmsProductionRun;
    use IsolatesDataMigrations;
    use ReportsMigrationFailures;
    use ResolvesMigrationPaths;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:rollback
                            {--step=0 : Number of migrations to rollback}
                            {--batch= : Rollback a specific batch number}
                            {--force : Force the operation to run in production}
                            {--path=* : The path(s) to the data migration files to use}
                            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}';

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
        return $this->usingMigrationPaths(fn (): int => $this->rollbackMigrations());
    }

    /**
     * Roll back the targeted migrations found in the resolved paths.
     *
     * @return int
     */
    protected function rollbackMigrations(): int
    {
        if (! $this->confirmToProceed('You are about to roll back data migrations in production. This may cause data loss.')) {
            return self::FAILURE;
        }

        $this->migrator->setOutput(new ConsoleOutput($this->output));

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
            $migrations = $this->migrator->getRepository()->getRollbackableByBatch($batch);

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
        } catch (Throwable $exception) {
            return $this->reportFailure($exception, 'Rollback error');
        }
    }
}
