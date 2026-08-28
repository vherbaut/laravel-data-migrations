<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Migration;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;
use Vherbaut\DataMigrations\Exceptions\InvalidMigrationException;
use Vherbaut\DataMigrations\Exceptions\MigrationNotFoundException;

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
     * Directories overriding the configured path, empty when none was given.
     *
     * @var array<int, string>
     */
    protected array $paths = [];

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
     * Get all migration files from the current paths, sorted by file name.
     *
     * @return array<int, string>
     */
    public function getMigrationFiles(): array
    {
        $files = [];

        foreach ($this->getPaths() as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = array_merge($files, $this->files->glob($path.'/*_*.php'));
        }

        usort($files, fn (string $left, string $right): int => strcmp(basename($left), basename($right)));

        return $files;
    }

    /**
     * Replace the directories to scan. An empty list restores the configured path.
     *
     * @param array<int, string> $paths
     * @return static
     */
    public function setPaths(array $paths): static
    {
        $this->paths = array_values($paths);

        return $this;
    }

    /**
     * Get the directories currently scanned.
     *
     * @return array<int, string>
     */
    public function getPaths(): array
    {
        return $this->paths === [] ? [$this->path] : $this->paths;
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
     * A named class is instantiated when it is declared in this very file, so an
     * application class sharing the name is never mistaken for the migration.
     * Otherwise the file is included once and must return the migration instance.
     *
     * @param string $file
     * @return MigrationInterface
     * @throws MigrationNotFoundException
     * @throws InvalidMigrationException
     */
    public function resolve(string $file): MigrationInterface
    {
        if (! $this->files->exists($file)) {
            throw MigrationNotFoundException::forFile($file);
        }

        $class = $this->getMigrationClass($file);

        if ($this->isDeclaredIn($class, $file)) {
            return $this->ensureMigration(new $class, $file);
        }

        $migration = $this->files->getRequire($file);

        if (is_object($migration)) {
            return $this->ensureMigration($migration, $file);
        }

        if ($this->isDeclaredIn($class, $file)) {
            return $this->ensureMigration(new $class, $file);
        }

        throw InvalidMigrationException::forFile($file, "it neither returns a migration instance nor declares the class {$class}");
    }

    /**
     * Determine if the class exists and was declared in the given file.
     * The answer changes once the file has been included.
     *
     * @param string $class
     * @param string $file
     * @return bool
     *
     * @phpstan-impure
     */
    protected function isDeclaredIn(string $class, string $file): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return (new ReflectionClass($class))->getFileName() === realpath($file);
    }

    /**
     * Ensure the resolved object is a data migration.
     *
     * @param object $candidate
     * @param string $file
     * @return MigrationInterface
     * @throws InvalidMigrationException
     */
    protected function ensureMigration(object $candidate, string $file): MigrationInterface
    {
        if (! $candidate instanceof MigrationInterface) {
            throw InvalidMigrationException::forFile($file, $candidate::class.' does not implement '.MigrationInterface::class);
        }

        return $candidate;
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
