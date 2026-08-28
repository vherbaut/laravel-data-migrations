<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Exceptions;

/**
 * Exception thrown when the backup required before a migration cannot be made.
 */
class BackupFailedException extends MigrationException
{
    /**
     * @param string $migration
     * @param string $reason
     * @return self
     */
    public static function forMigration(string $migration, string $reason): self
    {
        return new self("Backup before migration {$migration} failed: {$reason}");
    }

    /**
     * @return self
     */
    public static function becauseUnavailable(): self
    {
        return new self('Auto backup is enabled but spatie/laravel-backup is not installed. Install it or set data-migrations.safety.auto_backup to false.');
    }
}
