<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;
use Vherbaut\DataMigrations\Exceptions\BackupFailedException;

/**
 * Backs up the database with spatie/laravel-backup (backup:run --only-db).
 */
class SpatieBackupService implements BackupServiceInterface
{
    /**
     * @param string $migrationName
     * @return void
     * @throws BackupFailedException
     */
    public function backup(string $migrationName): void
    {
        try {
            $exitCode = Artisan::call('backup:run', [
                '--only-db' => true,
                '--disable-notifications' => true,
            ]);
        } catch (Throwable $exception) {
            throw BackupFailedException::forMigration($migrationName, $exception->getMessage());
        }

        if ($exitCode !== 0) {
            throw BackupFailedException::forMigration($migrationName, "backup:run exited with code {$exitCode}");
        }

        Log::info("[DataMigration] Backup created before migration: {$migrationName}");
    }
}
