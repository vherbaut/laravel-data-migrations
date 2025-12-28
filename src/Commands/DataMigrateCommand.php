<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Throwable;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Exceptions\MigrationException;

/**
 * Command to run pending data migrations.
 */
class DataMigrateCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:migrate
                            {--dry-run : Show what would be migrated without actually running}
                            {--force : Force the operation to run in production}
                            {--step : Run migrations one at a time}
                            {--no-confirm : Skip row count confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run pending data migrations';

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
            $this->error('Data migrations table not found. Run: php artisan migrate');

            return self::FAILURE;
        }

        $options = [
            'dry-run' => (bool) $this->option('dry-run'),
            'step' => (bool) $this->option('step'),
        ];

        if ($options['dry-run']) {
            $this->info('');
            $this->info('=== DRY RUN MODE ===');
            $this->info('');
        }

        // Check confirm threshold before running
        if (! $options['dry-run'] && ! $this->confirmRowThreshold()) {
            return self::FAILURE;
        }

        try {
            $migrations = $this->migrator->run($options);

            if (count($migrations) === 0 && ! $options['dry-run']) {
                return self::SUCCESS;
            }

            if ($options['dry-run']) {
                $this->info('');
                $this->info(count($migrations).' migration(s) would run.');
            }

            return self::SUCCESS;
        } catch (MigrationException $e) {
            $this->error("Migration error: {$e->getMessage()}");

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
        if ($this->option('dry-run')) {
            return true;
        }

        /** @var bool $shouldConfirm */
        $shouldConfirm = config('data-migrations.safety.require_force_in_production', true);

        if ($shouldConfirm && app()->environment('production')) {
            return (bool) $this->option('force') || $this->confirm(
                'You are about to run data migrations in production. Do you wish to continue?'
            );
        }

        return true;
    }

    /**
     * Confirm if estimated rows exceed the threshold.
     *
     * @return bool
     */
    protected function confirmRowThreshold(): bool
    {
        if ($this->option('no-confirm') || $this->option('force')) {
            return true;
        }

        /** @var int $threshold */
        $threshold = config('data-migrations.safety.confirm_threshold', 10000);

        if ($threshold === 0) {
            return true;
        }

        $pendingMigrations = $this->migrator->getPendingMigrations();
        $totalEstimatedRows = 0;

        foreach ($pendingMigrations as $file) {
            $migration = $this->migrator->resolve($file);
            $estimatedRows = $migration->getEstimatedRows();

            if ($estimatedRows !== null) {
                $totalEstimatedRows += $estimatedRows;
            }
        }

        if ($totalEstimatedRows > $threshold) {
            $this->warn("Estimated rows to be affected: {$totalEstimatedRows}");
            $this->warn("This exceeds the confirmation threshold of {$threshold} rows.");

            return $this->confirm('Do you wish to continue?');
        }

        return true;
    }
}
