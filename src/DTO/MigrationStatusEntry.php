<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\DTO;

use DateTimeInterface;
use Vherbaut\DataMigrations\Enums\MigrationStatus;

/**
 * One row of data:status, for a migration file or an orphaned record.
 */
final readonly class MigrationStatusEntry
{
    /**
     * @param string $name
     * @param MigrationStatus $status
     * @param int|null $batch
     * @param int|null $rowsAffected
     * @param int|null $durationMs
     * @param DateTimeInterface|null $ranAt
     * @param bool $orphaned
     */
    public function __construct(
        public string $name,
        public MigrationStatus $status,
        public ?int $batch,
        public ?int $rowsAffected,
        public ?int $durationMs,
        public ?DateTimeInterface $ranAt,
        public bool $orphaned,
    ) {}

    /**
     * Create the entry of a migration file without tracking record.
     *
     * @param string $name
     * @return self
     */
    public static function pending(string $name): self
    {
        return new self($name, MigrationStatus::Pending, null, null, null, null, false);
    }

    /**
     * Create the entry of a tracking record.
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
        return $this->status === MigrationStatus::Pending;
    }

    /**
     * The JSON shape printed by data:status --json.
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
            'status' => $this->status->value,
            'batch' => $this->batch,
            'rows_affected' => $this->rowsAffected,
            'duration_ms' => $this->durationMs,
            'ran_at' => $this->ranAt?->format('Y-m-d H:i:s'),
            'orphaned' => $this->orphaned,
        ];
    }
}
