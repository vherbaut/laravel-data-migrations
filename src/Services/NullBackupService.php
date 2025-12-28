<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Services;

use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;

/**
 * Null backup service when no backup implementation is available.
 */
class NullBackupService implements BackupServiceInterface
{
    /**
     * Backup the specified tables before migration.
     *
     * @param array<int, string> $tables
     * @param string $migrationName
     * @return bool
     */
    public function backupTables(array $tables, string $migrationName): bool
    {
        // No-op: backup not available
        return false;
    }

    /**
     * Check if the backup service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return false;
    }
}
