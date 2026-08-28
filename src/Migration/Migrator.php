<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Migration;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Collection;
use Throwable;
use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;
use Vherbaut\DataMigrations\Contracts\MigrationOutput;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Contracts\Reversible;
use Vherbaut\DataMigrations\DTO\MigrationRecord;
use Vherbaut\DataMigrations\Events\DataMigrationEnded;
use Vherbaut\DataMigrations\Events\DataMigrationFailed;
use Vherbaut\DataMigrations\Events\DataMigrationStarted;
use Vherbaut\DataMigrations\Events\NoPendingDataMigrations;
use Vherbaut\DataMigrations\Exceptions\UnresolvedMigrationsException;
use Vherbaut\DataMigrations\Output\NullOutput;

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
     * The event dispatcher, when events are wanted.
     *
     * @var Dispatcher|null
     */
    protected ?Dispatcher $events = null;

    /**
     * Selects the records a rollback targets.
     *
     * @var RollbackTargetSelector
     */
    protected RollbackTargetSelector $rollbackTargets;

    /**
     * Where messages are written.
     *
     * @var MigrationOutput
     */
    protected MigrationOutput $output;

    /**
     * Create a new migrator instance.
     *
     * @param MigrationRepositoryInterface $repository
     * @param ConnectionResolverInterface $resolver
     * @param MigrationFileResolverInterface $fileResolver
     * @param BackupServiceInterface $backupService
     * @param Dispatcher|null $events
     */
    public function __construct(
        MigrationRepositoryInterface $repository,
        ConnectionResolverInterface $resolver,
        MigrationFileResolverInterface $fileResolver,
        BackupServiceInterface $backupService,
        ?Dispatcher $events = null
    ) {
        $this->repository = $repository;
        $this->resolver = $resolver;
        $this->fileResolver = $fileResolver;
        $this->backupService = $backupService;
        $this->events = $events;
        $this->output = new NullOutput;
        $this->rollbackTargets = new RollbackTargetSelector($repository);
    }

    /**
     * Run the pending migrations.
     *
     * Migrations recorded as failed, or as still running after their process
     * died, block the run unless the "retry-failed" option is set or the
     * migration is idempotent.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     * @throws UnresolvedMigrationsException
     * @throws Throwable
     */
    public function run(array $options = []): array
    {
        $retryUnresolved = (bool) ($options['retry-failed'] ?? false);

        if (! $retryUnresolved) {
            $this->ensureNothingIsUnresolved();
        }

        $migrations = $this->migrationsToRun($retryUnresolved);

        if (count($migrations) === 0) {
            $this->output->info('Nothing to migrate.');
            $this->fireEvent(new NoPendingDataMigrations('up'));

            return [];
        }

        $batch = $this->repository->getNextBatchNumber();
        $step = (bool) ($options['step'] ?? false);

        $this->output->info('Running data migrations...');

        $ran = [];

        foreach ($migrations as $file) {
            $this->runMigration($file, $batch, $options);
            $ran[] = $file;

            if ($step) {
                $batch++;
            }
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

        $migration->setOutput($this->output);

        $this->output->line("Migrating: {$name}");
        $this->backupService->backup($name);

        $this->repository->logStart($name, $batch);
        $this->fireEvent(new DataMigrationStarted($migration, $name, 'up'));
        $startTime = microtime(true);

        try {
            $this->runMigrationUp($migration);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->repository->logComplete(
                $name,
                $migration->getRowsAffected(),
                $durationMs,
                ['description' => $migration->getDescription()]
            );

            $this->output->info("Migrated: {$name} ({$durationMs}ms, {$migration->getRowsAffected()} rows)");
            $this->fireEvent(new DataMigrationEnded($migration, $name, 'up', $migration->getRowsAffected(), $durationMs));
        } catch (Throwable $e) {
            $this->repository->logFailed($name, $e->getMessage());
            $this->output->error("Failed: {$name} - {$e->getMessage()}");
            $this->fireEvent(new DataMigrationFailed($migration, $name, 'up', $e));

            throw $e;
        }
    }

    /**
     * Run the migration's up method.
     *
     * @param MigrationInterface $migration
     * @return void
     * @throws Throwable
     */
    protected function runMigrationUp(MigrationInterface $migration): void
    {
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
     * Rollback the last batch of migrations.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function rollback(array $options = []): array
    {
        $migrations = $this->rollbackTargets->select($options);

        if ($migrations->isEmpty()) {
            $this->output->info('Nothing to rollback.');
            $this->fireEvent(new NoPendingDataMigrations('down'));

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
                $this->output->warn("Migration file not found: {$migration->migration}");

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

        if (! $instance instanceof Reversible) {
            $reason = method_exists($instance, 'down')
                ? 'declares down() but does not implement Reversible'
                : 'not reversible';
            $this->output->warn("Skipping ({$reason}): {$migration->migration}");

            return false;
        }

        $instance->setOutput($this->output);

        $this->output->line("Rolling back: {$migration->migration}");
        $this->fireEvent(new DataMigrationStarted($instance, $migration->migration, 'down'));

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
            $this->output->info("Rolled back: {$migration->migration} ({$durationMs}ms)");
            $this->fireEvent(new DataMigrationEnded($instance, $migration->migration, 'down', $instance->getRowsAffected(), $durationMs));

            return true;
        } catch (Throwable $e) {
            $this->output->error("Rollback failed: {$migration->migration} - {$e->getMessage()}");
            $this->fireEvent(new DataMigrationFailed($instance, $migration->migration, 'down', $e));

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
        return $this->migrationsToRun(false);
    }

    /**
     * Get the files to run: never completed, and among the unresolved ones only
     * the idempotent migrations unless every unresolved migration is retried.
     *
     * @param bool $retryUnresolved
     * @return array<int, string>
     */
    protected function migrationsToRun(bool $retryUnresolved): array
    {
        $completed = $this->repository->getRan();
        $unresolved = $this->unresolvedNames();

        return Collection::make($this->getMigrationFiles())
            ->reject(fn (string $file): bool => in_array($this->getMigrationName($file), $completed, true))
            ->filter(function (string $file) use ($unresolved, $retryUnresolved): bool {
                if (! in_array($this->getMigrationName($file), $unresolved, true)) {
                    return true;
                }

                if ($retryUnresolved) {
                    return true;
                }

                return $this->resolve($file)->isIdempotent();
            })
            ->values()
            ->all();
    }

    /**
     * Refuse to run while a non idempotent migration with a file is unresolved.
     *
     * @return void
     * @throws UnresolvedMigrationsException
     */
    protected function ensureNothingIsUnresolved(): void
    {
        $blocking = $this->getBlockingMigrations();

        if ($blocking !== []) {
            throw UnresolvedMigrationsException::forMigrations($blocking);
        }
    }

    /**
     * Names of the unresolved migrations that block a run: they have a file
     * and are not idempotent.
     *
     * @return array<int, string>
     */
    public function getBlockingMigrations(): array
    {
        $unresolved = $this->unresolvedNames();

        return Collection::make($this->getMigrationFiles())
            ->filter(fn (string $file): bool => in_array($this->getMigrationName($file), $unresolved, true))
            ->reject(fn (string $file): bool => $this->resolve($file)->isIdempotent())
            ->map(fn (string $file): string => $this->getMigrationName($file))
            ->values()
            ->all();
    }

    /**
     * Names of the migrations recorded as failed or still running.
     *
     * @return array<int, string>
     */
    protected function unresolvedNames(): array
    {
        return $this->repository->getUnresolved()
            ->map(fn (MigrationRecord $record): string => $record->migration)
            ->all();
    }

    /**
     * Get the tracking records whose migration file no longer exists.
     *
     * @return Collection<int, MigrationRecord>
     */
    public function getOrphanedMigrations(): Collection
    {
        $names = array_map(
            fn (string $file): string => $this->getMigrationName($file),
            $this->getMigrationFiles(),
        );

        return $this->repository->getMigrations()
            ->reject(fn (MigrationRecord $record): bool => in_array($record->migration, $names, true))
            ->values();
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
     * Dispatch an event when a dispatcher was provided.
     *
     * @param object $event
     * @return void
     */
    protected function fireEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }

    /**
     * Set where messages and progress are written.
     *
     * @param MigrationOutput $output
     * @return static
     */
    public function setOutput(MigrationOutput $output): static
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
