<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Services;

use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;

/**
 * Backup service bound when auto backup is off: does nothing.
 */
class NullBackupService implements BackupServiceInterface
{
    /**
     * @param string $migrationName
     * @return void
     */
    public function backup(string $migrationName): void {}
}
