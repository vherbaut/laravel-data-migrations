<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands\Concerns;

use Closure;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;

/**
 * Resolves the --path and --realpath options the way "artisan migrate" does.
 */
trait ResolvesMigrationPaths
{
    /**
     * Run the handler with the --path directories applied to the file resolver, then restore the previous ones.
     *
     * @param Closure(): int $handler
     * @return int
     */
    protected function usingMigrationPaths(Closure $handler): int
    {
        $resolver = $this->laravel->make(MigrationFileResolverInterface::class);
        $previous = $resolver->getPaths();

        $resolver->setPaths($this->getMigrationPaths());

        try {
            return $handler();
        } finally {
            $resolver->setPaths($previous);
        }
    }

    /**
     * Get the directories given by --path, absolute or relative to the base path. Empty when the option is absent.
     *
     * @return array<int, string>
     */
    protected function getMigrationPaths(): array
    {
        /** @var array<int, string> $paths */
        $paths = (array) $this->option('path');

        return array_map(fn (string $path): string => $this->resolveMigrationPath($path), $paths);
    }

    /**
     * Get the directory configured for data migrations.
     *
     * @return string
     */
    protected function defaultMigrationPath(): string
    {
        /** @var string $path */
        $path = config('data-migrations.path');

        return $path;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function resolveMigrationPath(string $path): string
    {
        if ($this->option('realpath')) {
            return $path;
        }

        return $this->laravel->basePath($path);
    }
}
