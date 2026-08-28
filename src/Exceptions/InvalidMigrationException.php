<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Exceptions;

/**
 * Exception thrown when a migration file does not produce a data migration.
 */
class InvalidMigrationException extends MigrationException
{
    /**
     * Create a new exception for a file that cannot be resolved to a migration.
     *
     * @param string $file
     * @param string $reason
     * @return self
     */
    public static function forFile(string $file, string $reason): self
    {
        return new self("Migration file {$file} is not a data migration: {$reason}.");
    }
}
