<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

use Vherbaut\DataMigrations\Exceptions\BackupFailedException;

/**
 * Backs up the database before a data migration runs.
 */
interface BackupServiceInterface
{
    /**
     * Create a backup, or throw when it cannot be made: the migration then does not start.
     *
     * @param string $migrationName
     * @return void
     * @throws BackupFailedException
     */
    public function backup(string $migrationName): void;
}
