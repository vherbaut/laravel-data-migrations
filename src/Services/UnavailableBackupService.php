<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Services;

use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;
use Vherbaut\DataMigrations\Exceptions\BackupFailedException;

/**
 * Backup service bound when auto backup is on but spatie/laravel-backup is
 * missing: refuses to let any migration run.
 */
class UnavailableBackupService implements BackupServiceInterface
{
    /**
     * @param string $migrationName
     * @return void
     * @throws BackupFailedException
     */
    public function backup(string $migrationName): void
    {
        throw BackupFailedException::becauseUnavailable();
    }
}
