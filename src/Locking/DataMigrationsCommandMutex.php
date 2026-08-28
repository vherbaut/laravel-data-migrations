<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Locking;

use Illuminate\Console\CacheCommandMutex;
use Illuminate\Console\Command;

/**
 * Cache mutex shared by every data migration command.
 *
 * The lock name does not depend on the command, so data:migrate, data:rollback
 * and data:fresh exclude each other. The mutex is reentrant within the current
 * process because data:fresh runs data:migrate through a nested call while it
 * already holds the lock.
 */
class DataMigrationsCommandMutex extends CacheCommandMutex
{
    public const LOCK_NAME = 'data-migrations';

    /**
     * How many nested commands currently hold the lock in this process.
     *
     * @var int
     */
    protected int $depth = 0;

    /**
     * Attempt to obtain the shared lock, or reenter it when this process already holds it.
     *
     * @param Command $command
     * @return bool
     */
    public function create($command): bool
    {
        if ($this->depth > 0) {
            $this->depth++;

            return true;
        }

        if (! parent::create($command)) {
            return false;
        }

        $this->depth = 1;

        return true;
    }

    /**
     * Release the shared lock once the outermost command is done with it.
     *
     * The parent method returns void for lock providers, so no native return type is declared.
     *
     * @param Command $command
     * @return bool|null
     */
    public function forget($command)
    {
        if ($this->depth > 1) {
            $this->depth--;

            return true;
        }

        $this->depth = 0;

        return parent::forget($command);
    }

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
