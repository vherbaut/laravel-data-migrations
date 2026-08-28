<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\DTO\MigrationRecord;
use Vherbaut\DataMigrations\DTO\MigrationStatusEntry;
use Vherbaut\DataMigrations\Enums\MigrationStatus;

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
                            {--ran : Only show ran migrations}
                            {--json : Output the status list as JSON}';

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

        if ($this->option('json')) {
            $this->displayJson($migrations);

            return self::SUCCESS;
        }

        if ($migrations->isEmpty()) {
            $this->info('No migrations found.');

            return self::SUCCESS;
        }

        $this->displayTable($migrations);
        $this->displaySummary($migrations);

        return self::SUCCESS;
    }

    /**
     * Build the migration status list: every file, then every record whose file is gone.
     *
     * @param array<int, string> $files
     * @param Collection<int, MigrationRecord> $ran
     * @return Collection<int, MigrationStatusEntry>
     */
    protected function buildMigrationStatusList(array $files, Collection $ran): Collection
    {
        $tracked = Collection::make($files)->map(function (string $file) use ($ran): MigrationStatusEntry {
            $name = $this->migrator->getMigrationName($file);
            $record = $ran->first(fn (MigrationRecord $record): bool => $record->migration === $name);

            return $record === null
                ? MigrationStatusEntry::pending($name)
                : MigrationStatusEntry::fromRecord($record, false);
        });

        $orphaned = $this->migrator->getOrphanedMigrations()
            ->map(fn (MigrationRecord $record): MigrationStatusEntry => MigrationStatusEntry::fromRecord($record, true));

        return $tracked->concat($orphaned)->values();
    }

    /**
     * Apply filters based on command options.
     *
     * @param Collection<int, MigrationStatusEntry> $migrations
     * @return Collection<int, MigrationStatusEntry>
     */
    protected function applyFilters(Collection $migrations): Collection
    {
        if ($this->option('pending')) {
            return $migrations->filter(fn (MigrationStatusEntry $migration): bool => $migration->isPending())->values();
        }

        if ($this->option('ran')) {
            return $migrations->filter(fn (MigrationStatusEntry $migration): bool => ! $migration->isPending())->values();
        }

        return $migrations;
    }

    /**
     * Display the migrations as a JSON array.
     *
     * @param Collection<int, MigrationStatusEntry> $migrations
     * @return void
     */
    protected function displayJson(Collection $migrations): void
    {
        $this->line(json_encode(
            $migrations->map(fn (MigrationStatusEntry $migration): array => $migration->toArray())->all(),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * Display the migrations table.
     *
     * @param Collection<int, MigrationStatusEntry> $migrations
     * @return void
     */
    protected function displayTable(Collection $migrations): void
    {
        $this->table(
            ['Migration', 'Batch', 'Status', 'Rows', 'Duration', 'Ran At'],
            $migrations->map(fn (MigrationStatusEntry $migration): array => [
                $migration->name,
                $migration->batch !== null ? (string) $migration->batch : '-',
                $this->formatStatus($migration),
                $migration->rowsAffected !== null ? number_format($migration->rowsAffected) : '-',
                $migration->durationMs !== null ? "{$migration->durationMs}ms" : '-',
                $migration->ranAt?->format('Y-m-d H:i:s') ?? '-',
            ])->all()
        );
    }

    /**
     * Display the summary.
     *
     * @param Collection<int, MigrationStatusEntry> $migrations
     * @return void
     */
    protected function displaySummary(Collection $migrations): void
    {
        $this->newLine();

        $pending = $migrations->filter(fn (MigrationStatusEntry $migration): bool => $migration->isPending())->count();
        $completed = $migrations->filter(fn (MigrationStatusEntry $migration): bool => $migration->status === MigrationStatus::Completed)->count();
        $failed = $migrations->filter(fn (MigrationStatusEntry $migration): bool => $migration->status === MigrationStatus::Failed)->count();
        $orphaned = $migrations->filter(fn (MigrationStatusEntry $migration): bool => $migration->orphaned)->count();

        $this->info("Total: {$migrations->count()} | Pending: {$pending} | Completed: {$completed} | Failed: {$failed} | Orphaned: {$orphaned}");
    }

    /**
     * Format the status for display, flagging records whose file is gone.
     *
     * @param MigrationStatusEntry $migration
     * @return string
     */
    protected function formatStatus(MigrationStatusEntry $migration): string
    {
        $label = match ($migration->status) {
            MigrationStatus::Pending => '<fg=yellow>Pending</>',
            MigrationStatus::Running => '<fg=blue>Running</>',
            MigrationStatus::Completed => '<fg=green>Completed</>',
            MigrationStatus::Failed => '<fg=red>Failed</>',
            MigrationStatus::RolledBack => '<fg=gray>Rolled Back</>',
        };

        return $migration->orphaned
            ? "{$label} <fg=yellow>(orphaned)</>"
            : $label;
    }
}
