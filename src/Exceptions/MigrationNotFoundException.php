<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Exceptions;

/**
 * Exception thrown when a migration file cannot be found.
 */
class MigrationNotFoundException extends MigrationException
{
    /**
     * Create a new exception for a missing migration.
     *
     * @param string $migration
     * @return self
     */
    public static function forMigration(string $migration): self
    {
        return new self("Migration file not found: {$migration}");
    }
}
