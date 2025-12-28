<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

use Illuminate\Support\Collection;
use Vherbaut\DataMigrations\DTO\MigrationRecord;

/**
 * Contract for migration repository.
 */
interface MigrationRepositoryInterface
{
    /**
     * Get the ran migrations.
     *
     * @return array<int, string>
     */
    public function getRan(): array;

    /**
     * Get list of migrations.
     *
     * @param int|null $steps
     * @return Collection<int, MigrationRecord>
     */
    public function getMigrations(?int $steps = null): Collection;

    /**
     * Get the last migration batch.
     *
     * @return Collection<int, MigrationRecord>
     */
    public function getLast(): Collection;

    /**
     * Get migrations by batch number.
     *
     * @param int $batch
     * @return Collection<int, MigrationRecord>
     */
    public function getMigrationsByBatch(int $batch): Collection;

    /**
     * Log that a migration is starting.
     *
     * @param string $migration
     * @param int $batch
     * @return void
     */
    public function logStart(string $migration, int $batch): void;

    /**
     * Log that a migration completed successfully.
     *
     * @param string $migration
     * @param int $rowsAffected
     * @param int $durationMs
     * @param array<string, mixed> $metadata
     * @return void
     */
    public function logComplete(string $migration, int $rowsAffected, int $durationMs, array $metadata = []): void;

    /**
     * Log that a migration failed.
     *
     * @param string $migration
     * @param string $errorMessage
     * @return void
     */
    public function logFailed(string $migration, string $errorMessage): void;

    /**
     * Log that a migration was rolled back.
     *
     * @param string $migration
     * @return void
     */
    public function logRollback(string $migration): void;

    /**
     * Delete a migration record.
     *
     * @param string $migration
     * @return void
     */
    public function delete(string $migration): void;

    /**
     * Get the next batch number.
     *
     * @return int
     */
    public function getNextBatchNumber(): int;

    /**
     * Get the last batch number.
     *
     * @return int
     */
    public function getLastBatchNumber(): int;

    /**
     * Get a migration record by name.
     *
     * @param string $migration
     * @return MigrationRecord|null
     */
    public function getMigration(string $migration): ?MigrationRecord;

    /**
     * Check if a migration has been run.
     *
     * @param string $migration
     * @return bool
     */
    public function hasRun(string $migration): bool;

    /**
     * Check if the repository table exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool;

    /**
     * Set the connection to use.
     *
     * @param string|null $connection
     * @return static
     */
    public function setConnection(?string $connection): static;
}
