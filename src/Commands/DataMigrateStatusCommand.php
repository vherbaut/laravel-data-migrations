<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\DTO\MigrationRecord;

/**
 * Command to display data migration status.
 */
class DataMigrateStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:status
                            {--pending : Only show pending migrations}
                            {--ran : Only show ran migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of each data migration';

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
        $repository = $this->migrator->getRepository();

        if (! $repository->repositoryExists()) {
            $this->error('Data migrations table not found. Run: php artisan migrate');

            return self::FAILURE;
        }

        $ran = $repository->getMigrations();
        $files = $this->migrator->getMigrationFiles();

        $migrations = $this->buildMigrationStatusList($files, $ran);
        $migrations = $this->applyFilters($migrations);

        if ($migrations->isEmpty()) {
            $this->info('No migrations found.');

            return self::SUCCESS;
        }

        $this->displayTable($migrations);
        $this->displaySummary($migrations);

        return self::SUCCESS;
    }

    /**
     * Build the migration status list.
     *
     * @param array<int, string> $files
     * @param Collection<int, MigrationRecord> $ran
     * @return Collection<int, array{name: string, batch: int|null, status: string, rows: int|null, duration: int|null, ran_at: string|null}>
     */
    protected function buildMigrationStatusList(array $files, Collection $ran): Collection
    {
        return Collection::make($files)->map(function (string $file) use ($ran): array {
            $name = $this->migrator->getMigrationName($file);
            $record = $ran->first(fn (MigrationRecord $r): bool => $r->migration === $name);

            return [
                'name' => $name,
                'batch' => $record?->batch,
                'status' => $record !== null ? $record->status : 'pending',
                'rows' => $record?->rowsAffected,
                'duration' => $record?->durationMs,
                'ran_at' => $record?->completedAt?->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Apply filters based on command options.
     *
     * @param Collection<int, array{name: string, batch: int|null, status: string, rows: int|null, duration: int|null, ran_at: string|null}> $migrations
     * @return Collection<int, array{name: string, batch: int|null, status: string, rows: int|null, duration: int|null, ran_at: string|null}>
     */
    protected function applyFilters(Collection $migrations): Collection
    {
        if ($this->option('pending')) {
            /** @var Collection<int, array{name: string, batch: int|null, status: string, rows: int|null, duration: int|null, ran_at: string|null}> */
            return $migrations->filter(
                fn (array $m): bool => $m['status'] === 'pending'
            )->values();
        }

        if ($this->option('ran')) {
            /** @var Collection<int, array{name: string, batch: int|null, status: string, rows: int|null, duration: int|null, ran_at: string|null}> */
            return $migrations->filter(
                fn (array $m): bool => $m['status'] !== 'pending'
            )->values();
        }

        return $migrations;
    }

    /**
     * Display the migrations table.
     *
     * @param Collection<int, array{name: string, batch: int|null, status: string, rows: int|null, duration: int|null, ran_at: string|null}> $migrations
     * @return void
     */
    protected function displayTable(Collection $migrations): void
    {
        $this->table(
            ['Migration', 'Batch', 'Status', 'Rows', 'Duration', 'Ran At'],
            $migrations->map(fn (array $m): array => [
                $m['name'],
                $m['batch'] !== null ? (string) $m['batch'] : '-',
                $this->formatStatus($m['status']),
                $m['rows'] !== null ? number_format($m['rows']) : '-',
                $m['duration'] !== null ? $m['duration'].'ms' : '-',
                $m['ran_at'] ?? '-',
            ])->toArray()
        );
    }

    /**
     * Display the summary.
     *
     * @param Collection<int, array{name: string, batch: int|null, status: string, rows: int|null, duration: int|null, ran_at: string|null}> $migrations
     * @return void
     */
    protected function displaySummary(Collection $migrations): void
    {
        $this->newLine();

        $pending = $migrations->filter(fn (array $m): bool => $m['status'] === 'pending')->count();
        $completed = $migrations->filter(fn (array $m): bool => $m['status'] === 'completed')->count();
        $failed = $migrations->filter(fn (array $m): bool => $m['status'] === 'failed')->count();

        $this->info("Total: {$migrations->count()} | Pending: {$pending} | Completed: {$completed} | Failed: {$failed}");
    }

    /**
     * Format the status for display.
     *
     * @param string $status
     * @return string
     */
    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'pending' => '<fg=yellow>Pending</>',
            'running' => '<fg=blue>Running</>',
            'completed' => '<fg=green>Completed</>',
            'failed' => '<fg=red>Failed</>',
            'rolled_back' => '<fg=gray>Rolled Back</>',
            default => $status,
        };
    }
}
