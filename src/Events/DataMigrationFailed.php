<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Events;

use Throwable;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;

/**
 * Dispatched when a data migration's up() or down() method throws, right before the exception is rethrown.
 */
final class DataMigrationFailed
{
    /**
     * @param MigrationInterface $migration
     * @param string $name
     * @param string $method Either "up" or "down".
     * @param Throwable $exception
     */
    public function __construct(
        public readonly MigrationInterface $migration,
        public readonly string $name,
        public readonly string $method,
        public readonly Throwable $exception,
    ) {}
}
