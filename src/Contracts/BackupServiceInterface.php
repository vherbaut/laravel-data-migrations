<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

/**
 * Contract for backup services.
 */
interface BackupServiceInterface
{
    /**
     * Backup the specified tables before migration.
     *
     * @param array<int, string> $tables
     * @param string $migrationName
     * @return bool
     */
    public function backupTables(array $tables, string $migrationName): bool;

    /**
     * Check if the backup service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool;
}
