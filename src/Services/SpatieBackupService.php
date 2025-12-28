<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;

/**
 * Backup service using spatie/laravel-backup.
 */
class SpatieBackupService implements BackupServiceInterface
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
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            // Run backup with only-db option
            $exitCode = Artisan::call('backup:run', [
                '--only-db' => true,
                '--disable-notifications' => true,
            ]);

            if ($exitCode === 0) {
                Log::info("[DataMigration] Backup created before migration: {$migrationName}");

                return true;
            }

            Log::warning("[DataMigration] Backup command returned non-zero exit code: {$exitCode}");

            return false;
        } catch (Throwable $e) {
            Log::error("[DataMigration] Backup failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if the backup service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return class_exists(\Spatie\Backup\BackupServiceProvider::class);
    }
}
