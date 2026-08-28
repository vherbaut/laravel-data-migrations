<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Events;

use Vherbaut\DataMigrations\Contracts\MigrationInterface;

/**
 * Dispatched after a data migration's up() or down() method completed and its record was updated.
 */
final class DataMigrationEnded
{
    /**
     * @param MigrationInterface $migration
     * @param string $name
     * @param string $method Either "up" or "down".
     * @param int $rowsAffected
     * @param int $durationMs
     */
    public function __construct(
        public readonly MigrationInterface $migration,
        public readonly string $name,
        public readonly string $method,
        public readonly int $rowsAffected,
        public readonly int $durationMs,
    ) {}
}
