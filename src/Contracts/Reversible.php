<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

/**
 * Marks a data migration that data:rollback can revert.
 */
interface Reversible
{
    /**
     * Reverse the data migration.
     *
     * @return void
     */
    public function down(): void;
}
