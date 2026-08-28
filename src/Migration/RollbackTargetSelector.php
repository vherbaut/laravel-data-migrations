<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Migration;

use Illuminate\Support\Collection;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\DTO\MigrationRecord;

/**
 * Picks the tracking records a rollback targets from the batch, step or default options.
 */
class RollbackTargetSelector
{
    /**
     * @param MigrationRepositoryInterface $repository
     */
    public function __construct(
        protected MigrationRepositoryInterface $repository,
    ) {}

    /**
     * Select the records to roll back: a given batch, the last N migrations, or the last batch.
     *
     * @param array<string, mixed> $options
     * @return Collection<int, MigrationRecord>
     */
    public function select(array $options): Collection
    {
        if (isset($options['batch'])) {
            return $this->repository->getRollbackableByBatch((int) $options['batch']);
        }

        $steps = (int) ($options['step'] ?? 0);

        if ($steps > 0) {
            return $this->repository->getRollbackable($steps);
        }

        return $this->repository->getLast();
    }
}
