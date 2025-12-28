<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\DTO;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * Data Transfer Object for migration records.
 */
final readonly class MigrationRecord
{
    /**
     * @param int $id
     * @param string $migration
     * @param int $batch
     * @param string $status
     * @param int|null $rowsAffected
     * @param int|null $durationMs
     * @param string|null $errorMessage
     * @param array<string, mixed>|null $metadata
     * @param DateTimeInterface|null $startedAt
     * @param DateTimeInterface|null $completedAt
     * @param DateTimeInterface $createdAt
     * @param DateTimeInterface $updatedAt
     */
    public function __construct(
        public int $id,
        public string $migration,
        public int $batch,
        public string $status,
        public ?int $rowsAffected,
        public ?int $durationMs,
        public ?string $errorMessage,
        public ?array $metadata,
        public ?DateTimeInterface $startedAt,
        public ?DateTimeInterface $completedAt,
        public DateTimeInterface $createdAt,
        public DateTimeInterface $updatedAt,
    ) {}

    /**
     * Create a MigrationRecord from a database row object.
     *
     * @param object $record
     * @return self
     * @throws Exception
     */
    public static function fromDatabaseRecord(object $record): self
    {
        return new self(
            id: (int) $record->id,
            migration: (string) $record->migration,
            batch: (int) $record->batch,
            status: (string) $record->status,
            rowsAffected: isset($record->rows_affected) ? (int) $record->rows_affected : null,
            durationMs: isset($record->duration_ms) ? (int) $record->duration_ms : null,
            errorMessage: $record->error_message ?? null,
            metadata: isset($record->metadata) ? json_decode((string) $record->metadata, true) : null,
            startedAt: isset($record->started_at) ? new DateTimeImmutable((string) $record->started_at) : null,
            completedAt: isset($record->completed_at) ? new DateTimeImmutable((string) $record->completed_at) : null,
            createdAt: new DateTimeImmutable((string) $record->created_at),
            updatedAt: new DateTimeImmutable((string) $record->updated_at),
        );
    }

    /**
     * Check if the migration is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the migration is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if the migration is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the migration failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the migration was rolled back.
     *
     * @return bool
     */
    public function isRolledBack(): bool
    {
        return $this->status === 'rolled_back';
    }
}
