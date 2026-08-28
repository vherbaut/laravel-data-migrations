<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Testing;

use LogicException;
use PHPUnit\Framework\Assert;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Exceptions\MigrationNotFoundException;

/**
 * Test helpers to run single data migrations for real and assert their tracking status.
 *
 * Requires the tracking table in the test database (RefreshDatabase or a migrate call).
 */
trait InteractsWithDataMigrations
{
    /**
     * Run one data migration by name, in a batch of its own so it can be rolled back alone.
     *
     * @param string $name
     * @return void
     * @throws MigrationNotFoundException
     */
    protected function runDataMigration(string $name): void
    {
        $file = app(MigrationFileResolverInterface::class)->findMigrationFile($name);

        if ($file === null) {
            throw MigrationNotFoundException::forMigration($name);
        }

        $repository = app(MigrationRepositoryInterface::class);

        if ($repository->hasRun($name)) {
            throw new LogicException("The data migration [{$name}] already ran. Roll it back first with rollbackDataMigration().");
        }

        app(MigratorInterface::class)->runMigration($file, $repository->getNextBatchNumber());
    }

    /**
     * Run every pending data migration.
     *
     * @return array<int, string>
     */
    protected function runDataMigrations(): array
    {
        return app(MigratorInterface::class)->run();
    }

    /**
     * Roll back one data migration by name. It must be alone in its batch.
     *
     * @param string $name
     * @return void
     */
    protected function rollbackDataMigration(string $name): void
    {
        $repository = app(MigrationRepositoryInterface::class);
        $record = $repository->getMigration($name);

        if ($record === null) {
            throw new LogicException("The data migration [{$name}] has no tracking record to roll back.");
        }

        if (! $record->isCompleted()) {
            if (! $record->isRunning()) {
                throw new LogicException("The data migration [{$name}] cannot be rolled back from status [{$record->status}].");
            }
        }

        if ($repository->getRollbackableByBatch($record->batch)->count() !== 1) {
            throw new LogicException("The data migration [{$name}] shares batch {$record->batch} with other migrations. Run it with runDataMigration() so it gets a batch of its own.");
        }

        app(MigratorInterface::class)->rollback(['batch' => $record->batch]);
    }

    /**
     * @param string $name
     * @return void
     */
    protected function assertDataMigrationRan(string $name): void
    {
        Assert::assertSame('completed', $this->dataMigrationStatus($name), "The data migration [{$name}] did not complete.");
    }

    /**
     * @param string $name
     * @return void
     */
    protected function assertDataMigrationNotRan(string $name): void
    {
        Assert::assertNotContains($this->dataMigrationStatus($name), ['completed', 'running'], "The data migration [{$name}] ran.");
    }

    /**
     * @param string $name
     * @return void
     */
    protected function assertDataMigrationFailed(string $name): void
    {
        Assert::assertSame('failed', $this->dataMigrationStatus($name), "The data migration [{$name}] did not fail.");
    }

    /**
     * @param string $name
     * @return void
     */
    protected function assertDataMigrationRolledBack(string $name): void
    {
        Assert::assertSame('rolled_back', $this->dataMigrationStatus($name), "The data migration [{$name}] was not rolled back.");
    }

    /**
     * @param string $name
     * @return string|null
     */
    protected function dataMigrationStatus(string $name): ?string
    {
        return app(MigrationRepositoryInterface::class)->getMigration($name)?->status;
    }
}
