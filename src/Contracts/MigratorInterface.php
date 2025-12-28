<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

use Illuminate\Console\OutputStyle;

/**
 * Contract for data migrator.
 */
interface MigratorInterface
{
    /**
     * Run the pending migrations.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function run(array $options = []): array;

    /**
     * Run a single migration file.
     *
     * @param string $file
     * @param int $batch
     * @param array<string, mixed> $options
     * @return void
     */
    public function runMigration(string $file, int $batch, array $options = []): void;

    /**
     * Rollback the last batch of migrations.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function rollback(array $options = []): array;

    /**
     * Get pending migrations.
     *
     * @return array<int, string>
     */
    public function getPendingMigrations(): array;

    /**
     * Get all migration files.
     *
     * @return array<int, string>
     */
    public function getMigrationFiles(): array;

    /**
     * Resolve a migration instance from a file.
     *
     * @param string $file
     * @return MigrationInterface
     */
    public function resolve(string $file): MigrationInterface;

    /**
     * Get the migration name from file path.
     *
     * @param string $file
     * @return string
     */
    public function getMigrationName(string $file): string;

    /**
     * Get the notes.
     *
     * @return array<int, string>
     */
    public function getNotes(): array;

    /**
     * Set the output instance.
     *
     * @param OutputStyle $output
     * @return static
     */
    public function setOutput(OutputStyle $output): static;

    /**
     * Get the repository.
     *
     * @return MigrationRepositoryInterface
     */
    public function getRepository(): MigrationRepositoryInterface;
}
