<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\DTO;

use DateTimeInterface;

/**
 * Status of one data migration as reported by data:status, whether it comes from a file, a record or both.
 */
final readonly class MigrationStatus
{
    /**
     * @param string $name
     * @param string $status
     * @param int|null $batch
     * @param int|null $rowsAffected
     * @param int|null $durationMs
     * @param DateTimeInterface|null $ranAt
     * @param bool $orphaned True when a tracking record exists but its migration file is gone.
     */
    public function __construct(
        public string $name,
        public string $status,
        public ?int $batch,
        public ?int $rowsAffected,
        public ?int $durationMs,
        public ?DateTimeInterface $ranAt,
        public bool $orphaned,
    ) {}

    /**
     * Build the status of a migration file that has no tracking record yet.
     *
     * @param string $name
     * @return self
     */
    public static function pending(string $name): self
    {
        return new self($name, 'pending', null, null, null, null, false);
    }

    /**
     * Build the status of a tracked migration.
     *
     * @param MigrationRecord $record
     * @param bool $orphaned
     * @return self
     */
    public static function fromRecord(MigrationRecord $record, bool $orphaned): self
    {
        return new self(
            $record->migration,
            $record->status,
            $record->batch,
            $record->rowsAffected,
            $record->durationMs,
            $record->completedAt,
            $orphaned,
        );
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get the JSON representation used by data:status --json.
     *
     * @return array{
     *     name: string,
     *     status: string,
     *     batch: int|null,
     *     rows_affected: int|null,
     *     duration_ms: int|null,
     *     ran_at: string|null,
     *     orphaned: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'batch' => $this->batch,
            'rows_affected' => $this->rowsAffected,
            'duration_ms' => $this->durationMs,
            'ran_at' => $this->ranAt?->format('Y-m-d H:i:s'),
            'orphaned' => $this->orphaned,
        ];
    }
}
