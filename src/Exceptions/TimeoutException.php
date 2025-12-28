<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Exceptions;

/**
 * Exception thrown when a migration exceeds its timeout limit.
 */
class TimeoutException extends MigrationException
{
    /**
     * Create a new timeout exception.
     *
     * @param string $migration
     * @param int $timeout
     * @return self
     */
    public static function forMigration(string $migration, int $timeout): self
    {
        return new self(
            "Migration '{$migration}' exceeded the timeout limit of {$timeout} seconds."
        );
    }
}
