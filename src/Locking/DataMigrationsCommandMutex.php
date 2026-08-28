<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Locking;

use Illuminate\Console\CacheCommandMutex;
use Illuminate\Console\Command;

/**
 * Cache mutex shared by every data migration command.
 *
 * The lock name does not depend on the command, so data:migrate, data:rollback
 * and data:refresh exclude each other.
 */
class DataMigrationsCommandMutex extends CacheCommandMutex
{
    public const LOCK_NAME = 'data-migrations';

    /**
     * Get the lock name, identical for every data migration command.
     *
     * @param Command $command
     * @return string
     */
    protected function commandMutexName($command): string
    {
        return self::LOCK_NAME;
    }
}
