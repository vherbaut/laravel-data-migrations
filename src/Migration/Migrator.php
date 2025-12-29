<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Migration;

use Illuminate\Console\OutputStyle;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Collection;
use Throwable;
use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\DTO\MigrationRecord;

/**
 * Orchestrates data migration execution.
 */
class Migrator implements MigratorInterface
{
    /**
     * The migration repository implementation.
     *
     * @var MigrationRepositoryInterface
     */
    protected MigrationRepositoryInterface $repository;

    /**
     * The database connection resolver.
     *
     * @var ConnectionResolverInterface
     */
    protected ConnectionResolverInterface $resolver;

    /**
     * The migration file resolver.
     *
     * @var MigrationFileResolverInterface
     */
    protected MigrationFileResolverInterface $fileResolver;

    /**
     * The backup service.
     *
     * @var BackupServiceInterface
     */
    protected BackupServiceInterface $backupService;

    /**
     * The console output instance.
     *
     * @var OutputStyle|null
     */
    protected ?OutputStyle $output = null;

    /**
     * The notes collected during migration.
     *
     * @var array<int, string>
     */
    protected array $notes = [];

    /**
     * Create a new migrator instance.
     *
     * @param MigrationRepositoryInterface $repository
     * @param ConnectionResolverInterface $resolver
     * @param MigrationFileResolverInterface $fileResolver
     * @param BackupServiceInterface $backupService
     */
    public function __construct(
        MigrationRepositoryInterface $repository,
        ConnectionResolverInterface $resolver,
        MigrationFileResolverInterface $fileResolver,
        BackupServiceInterface $backupService
    ) {
        $this->repository = $repository;
        $this->resolver = $resolver;
        $this->fileResolver = $fileResolver;
        $this->backupService = $backupService;
    }

    /**
     * Run the pending migrations.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     * @throws Throwable
     */
    public function run(array $options = []): array
    {
        $this->notes = [];
        $migrations = $this->getPendingMigrations();

        if (count($migrations) === 0) {
            $this->note('<info>Nothing to migrate.</info>');

            return [];
        }

        $batch = $this->repository->getNextBatchNumber();

        $this->note('<info>Running data migrations...</info>');

        $ran = [];

        foreach ($migrations as $file) {
            $this->runMigration($file, $batch, $options);
            $ran[] = $file;
        }

        return $ran;
    }

    /**
     * Run a single migration file.
     *
     * @param string $file
     * @param int $batch
     * @param array<string, mixed> $options
     * @return void
     * @throws Throwable
     */
    public function runMigration(string $file, int $batch, array $options = []): void
    {
        $name = $this->getMigrationName($file);
        $migration = $this->resolve($file);

        if ($this->output !== null) {
            $migration->setOutput($this->output);
        }

        $dryRun = (bool) ($options['dry-run'] ?? false);

        if ($dryRun) {
            $this->runDryRun($name, $migration);

            return;
        }

        $this->note("<comment>Migrating:</comment> {$name}");

        $this->repository->logStart($name, $batch);
        $startTime = microtime(true);

        try {
            $this->runMigrationUp($migration, $name, $options);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->repository->logComplete(
                $name,
                $migration->getRowsAffected(),
                $durationMs,
                ['description' => $migration->getDescription()]
            );

            $this->note("<info>Migrated:</info> {$name} ({$durationMs}ms, {$migration->getRowsAffected()} rows)");
        } catch (Throwable $e) {
            $this->repository->logFailed($name, $e->getMessage());
            $this->note("<error>Failed:</error> {$name} - {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Run the migration's up method.
     *
     * @param MigrationInterface $migration
     * @param string $name
     * @param array<string, mixed> $options
     * @return void
     * @throws Throwable
     */
    protected function runMigrationUp(MigrationInterface $migration, string $name, array $options): void
    {
        $this->applyTimeout($migration);
        $this->runAutoBackup($migration, $name);

        $useTransaction = $this->shouldUseTransaction($migration);

        if ($useTransaction) {
            $this->resolver
                ->connection($migration->getConnection())
                ->transaction(function () use ($migration): void {
                    $migration->up();
                });
        } else {
            $migration->up();
        }
    }

    /**
     * Apply timeout limit for the migration.
     *
     * @param MigrationInterface $migration
     * @return void
     */
    protected function applyTimeout(MigrationInterface $migration): void
    {
        $timeout = $migration->getTimeout();

        // If migration timeout is 0, use config default
        if ($timeout === 0) {
            /** @var int $timeout */
            $timeout = config('data-migrations.timeout', 0);
        }

        // If timeout is null or 0, no limit
        if ($timeout === null || $timeout === 0) {
            return;
        }

        set_time_limit($timeout);
    }

    /**
     * Run auto backup if enabled.
     *
     * @param MigrationInterface $migration
     * @param string $name
     * @return void
     */
    protected function runAutoBackup(MigrationInterface $migration, string $name): void
    {
        /** @var bool $autoBackup */
        $autoBackup = config('data-migrations.safety.auto_backup', false);

        if (! $autoBackup) {
            return;
        }

        if (! $this->backupService->isAvailable()) {
            $this->note('<fg=yellow>Auto backup enabled but backup service not available.</>');

            return;
        }

        $tables = $migration->getAffectedTables();

        if (count($tables) === 0) {
            return;
        }

        $this->note('<comment>Creating backup before migration...</comment>');

        if ($this->backupService->backupTables($tables, $name)) {
            $this->note('<info>Backup created successfully.</info>');
        } else {
            $this->note('<fg=yellow>Backup failed, continuing with migration...</>');
        }
    }

    /**
     * Perform a dry run of the migration.
     *
     * @param string $name
     * @param MigrationInterface $migration
     * @return void
     */
    protected function runDryRun(string $name, MigrationInterface $migration): void
    {
        $info = $migration->dryRun();

        $this->note("<comment>[DRY RUN]</comment> {$name}");
        $this->note('  Description: '.($info['description'] !== '' ? $info['description'] : 'N/A'));
        $this->note('  Affected tables: '.(count($info['affected_tables']) > 0 ? implode(', ', $info['affected_tables']) : 'N/A'));
        $this->note('  Estimated rows: '.($info['estimated_rows'] ?? 'Unknown'));
        $this->note('  Reversible: '.($info['reversible'] ? 'Yes' : 'No'));
        $this->note('  Idempotent: '.($info['idempotent'] ? 'Yes' : 'No'));
        $this->note('  Uses transaction: '.($info['uses_transaction'] ? 'Yes' : 'No'));
        $this->note('');
    }

    /**
     * Rollback the last batch of migrations.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function rollback(array $options = []): array
    {
        $this->notes = [];
        $steps = (int) ($options['step'] ?? 0);
        $batch = isset($options['batch']) ? (int) $options['batch'] : null;

        // Determine which migrations to rollback
        if ($batch !== null) {
            $migrations = $this->repository->getMigrationsByBatch($batch);
        } elseif ($steps > 0) {
            $migrations = $this->repository->getMigrations($steps);
        } else {
            $migrations = $this->repository->getLast();
        }

        if ($migrations->isEmpty()) {
            $this->note('<info>Nothing to rollback.</info>');

            return [];
        }

        return $this->rollbackMigrations($migrations, $options);
    }

    /**
     * Rollback the given migrations.
     *
     * @param Collection<int, MigrationRecord> $migrations
     * @param array<string, mixed> $options
     * @return array<int, string>
     * @throws Throwable
     */
    protected function rollbackMigrations(Collection $migrations, array $options = []): array
    {
        $rolledBack = [];

        foreach ($migrations as $migration) {
            $file = $this->findMigrationFile($migration->migration);

            if ($file === null) {
                $this->note("<fg=yellow>Migration file not found:</> {$migration->migration}");

                continue;
            }

            if ($this->rollbackMigration($migration, $file, $options)) {
                $rolledBack[] = $file;
            }
        }

        return $rolledBack;
    }

    /**
     * Rollback a single migration.
     *
     * @param MigrationRecord $migration
     * @param string $file
     * @param array<string, mixed> $options
     * @return bool
     * @throws Throwable
     */
    protected function rollbackMigration(MigrationRecord $migration, string $file, array $options): bool
    {
        $instance = $this->resolve($file);

        if (! $instance->isReversible()) {
            $this->note("<fg=yellow>Skipping (not reversible):</> {$migration->migration}");

            return false;
        }

        if ($this->output !== null) {
            $instance->setOutput($this->output);
        }

        $this->note("<comment>Rolling back:</comment> {$migration->migration}");

        $startTime = microtime(true);

        try {
            $useTransaction = $this->shouldUseTransaction($instance);

            if ($useTransaction) {
                $this->resolver
                    ->connection($instance->getConnection())
                    ->transaction(function () use ($instance): void {
                        $instance->down();
                    });
            } else {
                $instance->down();
            }

            $this->repository->logRollback($migration->migration);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->note("<info>Rolled back:</info> {$migration->migration} ({$durationMs}ms)");

            return true;
        } catch (Throwable $e) {
            $this->note("<error>Rollback failed:</error> {$migration->migration} - {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Get pending migrations.
     *
     * @return array<int, string>
     */
    public function getPendingMigrations(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        return Collection::make($files)
            ->reject(fn (string $file): bool => in_array($this->getMigrationName($file), $ran, true))
            ->values()
            ->all();
    }

    /**
     * Get all migration files.
     *
     * @return array<int, string>
     */
    public function getMigrationFiles(): array
    {
        return $this->fileResolver->getMigrationFiles();
    }

    /**
     * Find a migration file by name.
     *
     * @param string $name
     * @return string|null
     */
    protected function findMigrationFile(string $name): ?string
    {
        return $this->fileResolver->findMigrationFile($name);
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param string $file
     * @return MigrationInterface
     */
    public function resolve(string $file): MigrationInterface
    {
        return $this->fileResolver->resolve($file);
    }

    /**
     * Get the migration name from file path.
     *
     * @param string $file
     * @return string
     */
    public function getMigrationName(string $file): string
    {
        return $this->fileResolver->getMigrationName($file);
    }

    /**
     * Determine if migration should use transaction.
     *
     * @param MigrationInterface $migration
     * @return bool
     */
    protected function shouldUseTransaction(MigrationInterface $migration): bool
    {
        /** @var string $configMode */
        $configMode = config('data-migrations.transaction', 'auto');

        return match ($configMode) {
            'always' => true,
            'never' => false,
            default => $migration->shouldRunInTransaction(),
        };
    }

    /**
     * Add a note/message.
     *
     * @param string $message
     * @return void
     */
    protected function note(string $message): void
    {
        $this->notes[] = $message;

        if ($this->output !== null) {
            $this->output->writeln($message);
        }
    }

    /**
     * Get the notes.
     *
     * @return array<int, string>
     */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /**
     * Set the output instance.
     *
     * @param OutputStyle $output
     * @return static
     */
    public function setOutput(OutputStyle $output): static
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get the repository.
     *
     * @return MigrationRepositoryInterface
     */
    public function getRepository(): MigrationRepositoryInterface
    {
        return $this->repository;
    }
}
