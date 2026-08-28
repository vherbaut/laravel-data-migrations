<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Events;

use Vherbaut\DataMigrations\Contracts\MigrationInterface;

/**
 * Dispatched right before a data migration's up() or down() method runs.
 */
final class DataMigrationStarted
{
    /**
     * @param MigrationInterface $migration
     * @param string $name
     * @param string $method Either "up" or "down".
     */
    public function __construct(
        public readonly MigrationInterface $migration,
        public readonly string $name,
        public readonly string $method,
    ) {}
}
