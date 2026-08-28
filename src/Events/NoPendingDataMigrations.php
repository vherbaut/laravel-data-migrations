<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Events;

/**
 * Dispatched when a run or a rollback finds nothing to do.
 */
final class NoPendingDataMigrations
{
    /**
     * @param string $method Either "up" or "down".
     */
    public function __construct(
        public readonly string $method,
    ) {}
}
