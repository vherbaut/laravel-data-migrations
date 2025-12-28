<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Migration;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;

/**
 * Resolves migration files and instances.
 */
class MigrationFileResolver implements MigrationFileResolverInterface
{
    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected Filesystem $files;

    /**
     * The path to migration files.
     *
     * @var string
     */
    protected string $path;

    /**
     * Create a new migration file resolver instance.
     *
     * @param Filesystem $files
     * @param string $path
     */
    public function __construct(Filesystem $files, string $path)
    {
        $this->files = $files;
        $this->path = $path;
    }

    /**
     * Get all migration files from the configured path.
     *
     * @return array<int, string>
     */
    public function getMigrationFiles(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = $this->files->glob($this->path.'/*_*.php');

        sort($files);

        return $files;
    }

    /**
     * Find a migration file by its name.
     *
     * @param string $name
     * @return string|null
     */
    public function findMigrationFile(string $name): ?string
    {
        $files = $this->getMigrationFiles();

        foreach ($files as $file) {
            if ($this->getMigrationName($file) === $name) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Resolve a migration instance from a file path.
     *
     * @param string $file
     * @return MigrationInterface
     */
    public function resolve(string $file): MigrationInterface
    {
        $class = $this->getMigrationClass($file);

        if (! class_exists($class)) {
            require_once $file;
        }

        // Handle anonymous classes (return new class extends DataMigration)
        $content = $this->files->get($file);

        if (Str::contains($content, 'return new class')) {
            /** @var MigrationInterface $migration */
            $migration = require $file;

            return $migration;
        }

        /** @var MigrationInterface $migration */
        $migration = new $class;

        return $migration;
    }

    /**
     * Get the migration name from a file path.
     *
     * @param string $file
     * @return string
     */
    public function getMigrationName(string $file): string
    {
        return str_replace('.php', '', basename($file));
    }

    /**
     * Get the migration class name from a file path.
     *
     * @param string $file
     * @return string
     */
    public function getMigrationClass(string $file): string
    {
        $name = $this->getMigrationName($file);

        // Remove timestamp prefix (YYYY_MM_DD_HHMMSS_)
        $withoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);

        return Str::studly($withoutTimestamp ?? $name);
    }
}
