<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Migration;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\DTO\MigrationRecord;
use Vherbaut\DataMigrations\Enums\MigrationStatus;

/**
 * Repository for tracking data migration execution state.
 */
class MigrationRepository implements MigrationRepositoryInterface
{
    /**
     * The database connection resolver.
     *
     * @var ConnectionResolverInterface
     */
    protected ConnectionResolverInterface $resolver;

    /**
     * The name of the migrations table.
     *
     * @var string
     */
    protected string $table;

    /**
     * The name of the database connection to use.
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * Create a new migration repository instance.
     *
     * @param ConnectionResolverInterface $resolver
     * @param string $table
     * @param string|null $connection
     */
    public function __construct(ConnectionResolverInterface $resolver, string $table, ?string $connection = null)
    {
        $this->resolver = $resolver;
        $this->table = $table;
        $this->connection = $connection;
    }

    /**
     * Get the ran migrations.
     *
     * @return array<int, string>
     */
    public function getRan(): array
    {
        return $this->table()
            ->where('status', MigrationStatus::Completed->value)
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('migration')
            ->all();
    }

    /**
     * Get list of migrations.
     *
     * @param int|null $steps
     * @return Collection<int, MigrationRecord>
     */
    public function getMigrations(?int $steps = null): Collection
    {
        $query = $this->table()
            ->orderByDesc('batch')
            ->orderByDesc('migration');

        if ($steps !== null) {
            $query->limit($steps);
        }

        return $query->get()->map(
            fn (object $record): MigrationRecord => MigrationRecord::fromDatabaseRecord($record)
        );
    }

    /**
     * Get the last migration batch (only completed/running migrations).
     *
     * @return Collection<int, MigrationRecord>
     */
    public function getLast(): Collection
    {
        $lastBatch = $this->table()
            ->where('status', MigrationStatus::Completed->value)
            ->max('batch');

        if ($lastBatch === null) {
            return collect();
        }

        return $this->table()
            ->where('batch', $lastBatch)
            ->where('status', MigrationStatus::Completed->value)
            ->orderByDesc('migration')
            ->get()
            ->map(
                fn (object $record): MigrationRecord => MigrationRecord::fromDatabaseRecord($record)
            );
    }

    /**
     * Get migrations by batch number.
     *
     * @param int $batch
     * @return Collection<int, MigrationRecord>
     */
    public function getMigrationsByBatch(int $batch): Collection
    {
        return $this->table()
            ->where('batch', $batch)
            ->orderByDesc('migration')
            ->get()
            ->map(
                fn (object $record): MigrationRecord => MigrationRecord::fromDatabaseRecord($record)
            );
    }

    /**
     * Get the migrations that can be rolled back (completed or running), most recent first.
     *
     * @param int|null $steps
     * @return Collection<int, MigrationRecord>
     */
    public function getRollbackable(?int $steps = null): Collection
    {
        $query = $this->table()
            ->where('status', MigrationStatus::Completed->value)
            ->orderByDesc('batch')
            ->orderByDesc('migration');

        if ($steps !== null) {
            $query->limit($steps);
        }

        return $query->get()->map(
            fn (object $record): MigrationRecord => MigrationRecord::fromDatabaseRecord($record)
        );
    }

    /**
     * Get the migrations of a batch that can be rolled back (completed or running).
     *
     * @param int $batch
     * @return Collection<int, MigrationRecord>
     */
    public function getRollbackableByBatch(int $batch): Collection
    {
        return $this->table()
            ->where('batch', $batch)
            ->where('status', MigrationStatus::Completed->value)
            ->orderByDesc('migration')
            ->get()
            ->map(
                fn (object $record): MigrationRecord => MigrationRecord::fromDatabaseRecord($record)
            );
    }

    /**
     * Get the records left without a known outcome: failed, or still running
     * after the process died.
     *
     * @return Collection<int, MigrationRecord>
     */
    public function getUnresolved(): Collection
    {
        return $this->table()
            ->whereIn('status', [MigrationStatus::Failed->value, MigrationStatus::Running->value])
            ->orderBy('batch')
            ->orderBy('migration')
            ->get()
            ->map(
                fn (object $record): MigrationRecord => MigrationRecord::fromDatabaseRecord($record)
            );
    }

    /**
     * Log that a migration is starting.
     *
     * @param string $migration
     * @param int $batch
     * @return void
     */
    public function logStart(string $migration, int $batch): void
    {
        $this->delete($migration);

        $this->table()->insert([
            'migration' => $migration,
            'batch' => $batch,
            'status' => MigrationStatus::Running->value,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log that a migration completed successfully.
     *
     * @param string $migration
     * @param int $rowsAffected
     * @param int $durationMs
     * @param array<string, mixed> $metadata
     * @return void
     */
    public function logComplete(string $migration, int $rowsAffected, int $durationMs, array $metadata = []): void
    {
        $this->table()
            ->where('migration', $migration)
            ->update([
                'status' => MigrationStatus::Completed->value,
                'rows_affected' => $rowsAffected,
                'duration_ms' => $durationMs,
                'metadata' => json_encode($metadata),
                'completed_at' => now(),
                'updated_at' => now(),
                'error_message' => null,
            ]);
    }

    /**
     * Log that a migration failed.
     *
     * @param string $migration
     * @param string $errorMessage
     * @return void
     */
    public function logFailed(string $migration, string $errorMessage): void
    {
        $this->table()
            ->where('migration', $migration)
            ->update([
                'status' => MigrationStatus::Failed->value,
                'error_message' => $errorMessage,
                'updated_at' => now(),
            ]);
    }

    /**
     * Log that a migration was rolled back.
     *
     * @param string $migration
     * @return void
     */
    public function logRollback(string $migration): void
    {
        $this->table()
            ->where('migration', $migration)
            ->update([
                'status' => MigrationStatus::RolledBack->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Delete a migration record.
     *
     * @param string $migration
     * @return void
     */
    public function delete(string $migration): void
    {
        $this->table()
            ->where('migration', $migration)
            ->delete();
    }

    /**
     * Get the next batch number.
     *
     * @return int
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last batch number.
     *
     * @return int
     */
    public function getLastBatchNumber(): int
    {
        return (int) $this->table()->max('batch');
    }

    /**
     * Get a migration record by name.
     *
     * @param string $migration
     * @return MigrationRecord|null
     * @throws Exception
     */
    public function getMigration(string $migration): ?MigrationRecord
    {
        $record = $this->table()
            ->where('migration', $migration)
            ->first();

        if ($record === null) {
            return null;
        }

        return MigrationRecord::fromDatabaseRecord($record);
    }

    /**
     * Check if a migration has been run.
     *
     * @param string $migration
     * @return bool
     */
    public function hasRun(string $migration): bool
    {
        return $this->table()
            ->where('migration', $migration)
            ->where('status', MigrationStatus::Completed->value)
            ->exists();
    }

    /**
     * Check if the repository table exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool
    {
        return $this->getDatabaseConnection()->getSchemaBuilder()->hasTable($this->table);
    }

    /**
     * Get the query builder for the table.
     *
     * @return Builder
     */
    protected function table(): Builder
    {
        return $this->getDatabaseConnection()->table($this->table);
    }

    /**
     * Get the database connection.
     *
     * @return Connection
     */
    protected function getDatabaseConnection(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->resolver->connection($this->connection);

        return $connection;
    }
}
