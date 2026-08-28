<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Testing;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Contracts\Reversible;
use Vherbaut\DataMigrations\DTO\MigrationRecord;
use Vherbaut\DataMigrations\Migration\RollbackTargetSelector;

/**
 * Migrator decorator for tests: records what would run or roll back without touching any data.
 *
 * Reads go to the real migrator, so the tracking table must exist in the test database.
 */
class MigratorFake implements MigratorInterface
{
    /**
     * The decorated migrator.
     *
     * @var MigratorInterface
     */
    protected MigratorInterface $migrator;

    /**
     * Selects the records a rollback would target.
     *
     * @var RollbackTargetSelector
     */
    protected RollbackTargetSelector $rollbackTargets;

    /**
     * Names of the migrations that would have run.
     *
     * @var array<int, string>
     */
    protected array $ran = [];

    /**
     * Names of the migrations that would have been rolled back.
     *
     * @var array<int, string>
     */
    protected array $rolledBack = [];

    /**
     * @param MigratorInterface $migrator
     */
    public function __construct(MigratorInterface $migrator)
    {
        $this->migrator = $migrator;
        $this->rollbackTargets = new RollbackTargetSelector($migrator->getRepository());
    }

    /**
     * Record the pending migrations as ran and return their files.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function run(array $options = []): array
    {
        $files = $this->migrator->getPendingMigrations();

        if ((bool) ($options['retry-failed'] ?? false)) {
            $files = $this->withUnresolvedFiles($files);
        }

        if ($this->isDryRun($options)) {
            return $files;
        }

        foreach ($files as $file) {
            $this->ran[] = $this->getMigrationName($file);
        }

        return $files;
    }

    /**
     * Record a single migration as ran.
     *
     * @param string $file
     * @param int $batch
     * @param array<string, mixed> $options
     * @return void
     */
    public function runMigration(string $file, int $batch, array $options = []): void
    {
        if ($this->isDryRun($options)) {
            return;
        }

        $this->ran[] = $this->getMigrationName($file);
    }

    /**
     * Record the migrations a rollback would revert and return their files.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function rollback(array $options = []): array
    {
        $rolledBack = [];

        foreach ($this->rollbackTargets->select($options) as $record) {
            $file = $this->findMigrationFile($record->migration);

            if ($file === null) {
                continue;
            }

            if (! $this->resolve($file) instanceof Reversible) {
                continue;
            }

            $this->rolledBack[] = $record->migration;
            $rolledBack[] = $file;
        }

        return $rolledBack;
    }

    /**
     * @return array<int, string>
     */
    public function getPendingMigrations(): array
    {
        return $this->migrator->getPendingMigrations();
    }

    /**
     * @return Collection<int, MigrationRecord>
     */
    public function getOrphanedMigrations(): Collection
    {
        return $this->migrator->getOrphanedMigrations();
    }

    /**
     * @return array<int, string>
     */
    public function getMigrationFiles(): array
    {
        return $this->migrator->getMigrationFiles();
    }

    /**
     * @param string $file
     * @return MigrationInterface
     */
    public function resolve(string $file): MigrationInterface
    {
        return $this->migrator->resolve($file);
    }

    /**
     * @param string $file
     * @return string
     */
    public function getMigrationName(string $file): string
    {
        return $this->migrator->getMigrationName($file);
    }

    /**
     * @return array<int, string>
     */
    public function getNotes(): array
    {
        return $this->migrator->getNotes();
    }

    /**
     * @param OutputStyle $output
     * @return static
     */
    public function setOutput(OutputStyle $output): static
    {
        $this->migrator->setOutput($output);

        return $this;
    }

    /**
     * @return MigrationRepositoryInterface
     */
    public function getRepository(): MigrationRepositoryInterface
    {
        return $this->migrator->getRepository();
    }

    /**
     * Names of the migrations recorded as ran, in order.
     *
     * @return array<int, string>
     */
    public function ran(): array
    {
        return $this->ran;
    }

    /**
     * Names of the migrations recorded as rolled back, in order.
     *
     * @return array<int, string>
     */
    public function rolledBack(): array
    {
        return $this->rolledBack;
    }

    /**
     * @param string $name
     * @return void
     */
    public function assertRan(string $name): void
    {
        Assert::assertTrue(
            in_array($name, $this->ran, true),
            "The data migration [{$name}] was not run.",
        );
    }

    /**
     * @param string $name
     * @return void
     */
    public function assertNotRan(string $name): void
    {
        Assert::assertFalse(
            in_array($name, $this->ran, true),
            "The data migration [{$name}] was run.",
        );
    }

    /**
     * @return void
     */
    public function assertNothingRan(): void
    {
        Assert::assertEmpty(
            $this->ran,
            'Data migrations were run: '.implode(', ', $this->ran),
        );
    }

    /**
     * @param string $name
     * @return void
     */
    public function assertRolledBack(string $name): void
    {
        Assert::assertTrue(
            in_array($name, $this->rolledBack, true),
            "The data migration [{$name}] was not rolled back.",
        );
    }

    /**
     * @return void
     */
    public function assertNothingRolledBack(): void
    {
        Assert::assertEmpty(
            $this->rolledBack,
            'Data migrations were rolled back: '.implode(', ', $this->rolledBack),
        );
    }

    /**
     * Add the files of the unresolved migrations, in file order.
     *
     * @param array<int, string> $files
     * @return array<int, string>
     */
    protected function withUnresolvedFiles(array $files): array
    {
        foreach ($this->migrator->getRepository()->getUnresolved() as $record) {
            $file = $this->findMigrationFile($record->migration);

            if ($file !== null) {
                $files[] = $file;
            }
        }

        $files = array_values(array_unique($files));
        usort($files, fn (string $left, string $right): int => strcmp(basename($left), basename($right)));

        return $files;
    }

    /**
     * @param array<string, mixed> $options
     * @return bool
     */
    protected function isDryRun(array $options): bool
    {
        return (bool) ($options['dry-run'] ?? false);
    }

    /**
     * @param string $name
     * @return string|null
     */
    protected function findMigrationFile(string $name): ?string
    {
        foreach ($this->getMigrationFiles() as $file) {
            if ($this->getMigrationName($file) === $name) {
                return $file;
            }
        }

        return null;
    }
}
