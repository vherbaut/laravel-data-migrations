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
 * Run the pending data migrations.
 */
class DataMigrateCommand extends Command implements Isolatable
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
    protected $signature = 'data:migrate
                            {--dry-run : Show what would be migrated without actually running}
                            {--force : Force the operation to run in production}
                            {--step : Force the migrations to be run so they can be rolled back individually}
                            {--no-confirm : Skip row count confirmation}
                            {--retry-failed : Run again the migrations recorded as failed, or still running after a crash}
                            {--path=* : The path(s) to the data migration files to use}
                            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run pending data migrations';

    /**
     * @var MigratorInterface
     */
    protected MigratorInterface $migrator;

    /**
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
        return $this->usingMigrationPaths(fn (): int => $this->runMigrations());
    }

    /**
     * @return int
     */
    protected function runMigrations(): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        if (! $this->confirmToProceed('You are about to run data migrations in production.')) {
            return self::FAILURE;
        }

        if (! $this->trackingTableExists()) {
            return self::FAILURE;
        }

        if (! $this->confirmRowThreshold()) {
            return self::FAILURE;
        }

        $this->migrator->setOutput(new ConsoleOutput($this->output));

        try {
            $this->migrator->run([
                'step' => (bool) $this->option('step'),
                'retry-failed' => (bool) $this->option('retry-failed'),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            return $this->reportFailure($exception, 'Migration error');
        }
    }

    /**
     * Describe the pending migrations without running anything.
     *
     * @return int
     */
    protected function dryRun(): int
    {
        if (! $this->trackingTableExists()) {
            return self::FAILURE;
        }

        $this->info('');
        $this->info('=== DRY RUN MODE ===');
        $this->info('');

        $pending = $this->migrator->getPendingMigrations();

        foreach ($pending as $file) {
            $this->describe($this->migrator->getMigrationName($file), $this->migrator->resolve($file)->dryRun());
        }

        $blocking = $this->migrator->getBlockingMigrations();

        if ($blocking !== []) {
            $this->warn('These failed or still running migrations block the run until --retry-failed is passed: '.implode(', ', $blocking));
        }

        $this->info('');
        $this->info(count($pending).' migration(s) would run.');

        return self::SUCCESS;
    }

    /**
     * @param string $name
     * @param array{
     *     description: string,
     *     affected_tables: array<int, string>,
     *     estimated_rows: int|null,
     *     reversible: bool,
     *     idempotent: bool,
     *     uses_transaction: bool
     * } $info
     * @return void
     */
    protected function describe(string $name, array $info): void
    {
        $this->line("<comment>[DRY RUN]</comment> {$name}");
        $this->line('  Description: '.($info['description'] !== '' ? $info['description'] : 'N/A'));
        $this->line('  Affected tables: '.(count($info['affected_tables']) > 0 ? implode(', ', $info['affected_tables']) : 'N/A'));
        $this->line('  Estimated rows: '.($info['estimated_rows'] ?? 'Unknown'));
        $this->line('  Reversible: '.($info['reversible'] ? 'Yes' : 'No'));
        $this->line('  Idempotent: '.($info['idempotent'] ? 'Yes' : 'No'));
        $this->line('  Uses transaction: '.($info['uses_transaction'] ? 'Yes' : 'No'));
        $this->line('');
    }

    /**
     * @return bool
     */
    protected function trackingTableExists(): bool
    {
        if ($this->migrator->getRepository()->repositoryExists()) {
            return true;
        }

        $this->error('Data migrations table not found. Run: php artisan migrate');

        return false;
    }

    /**
     * Ask for confirmation when the estimated rows exceed the configured threshold.
     *
     * @return bool
     */
    protected function confirmRowThreshold(): bool
    {
        if ($this->option('no-confirm')) {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        /** @var int $threshold */
        $threshold = config('data-migrations.safety.confirm_threshold', 10000);

        if ($threshold === 0) {
            return true;
        }

        $totalEstimatedRows = 0;

        foreach ($this->migrator->getPendingMigrations() as $file) {
            $totalEstimatedRows += $this->migrator->resolve($file)->getEstimatedRows() ?? 0;
        }

        if ($totalEstimatedRows <= $threshold) {
            return true;
        }

        $this->warn("Estimated rows to be affected: {$totalEstimatedRows}");
        $this->warn("This exceeds the confirmation threshold of {$threshold} rows.");

        return $this->confirm('Do you wish to continue?');
    }
}
