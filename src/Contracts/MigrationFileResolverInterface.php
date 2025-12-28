<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

/**
 * Contract for resolving migration files.
 */
interface MigrationFileResolverInterface
{
    /**
     * Get all migration files from the configured path.
     *
     * @return array<int, string>
     */
    public function getMigrationFiles(): array;

    /**
     * Find a migration file by its name.
     *
     * @param string $name
     * @return string|null
     */
    public function findMigrationFile(string $name): ?string;

    /**
     * Resolve a migration instance from a file path.
     *
     * @param string $file
     * @return MigrationInterface
     */
    public function resolve(string $file): MigrationInterface;

    /**
     * Get the migration name from a file path.
     *
     * @param string $file
     * @return string
     */
    public function getMigrationName(string $file): string;

    /**
     * Get the migration class name from a file path.
     *
     * @param string $file
     * @return string
     */
    public function getMigrationClass(string $file): string;
}
