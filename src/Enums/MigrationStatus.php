<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Enums;

/**
 * Status of a data migration, as persisted in the tracking table.
 * Pending is never persisted: a pending migration is one without a record.
 */
enum MigrationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    /**
     * Whether the migration stopped without a known outcome: it failed, or the
     * process recording it as running died before logging the result.
     *
     * @return bool
     */
    public function isUnresolved(): bool
    {
        return $this === self::Failed || $this === self::Running;
    }
}
