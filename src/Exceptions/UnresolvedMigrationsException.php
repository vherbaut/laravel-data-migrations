<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Exceptions;

/**
 * Exception thrown when data:migrate is asked to run while migrations are
 * recorded as failed, or as still running after their process died.
 */
class UnresolvedMigrationsException extends MigrationException
{
    /**
     * @param string $message
     * @param array<int, string> $migrations
     */
    public function __construct(string $message, protected array $migrations)
    {
        parent::__construct($message);
    }

    /**
     * @param array<int, string> $migrations
     * @return self
     */
    public static function forMigrations(array $migrations): self
    {
        $list = implode(', ', $migrations);

        return new self(
            "Cannot run data migrations while these are failed or still running: {$list}. "
            .'Check that no other process is running them, then pass --retry-failed to run them again.',
            $migrations,
        );
    }

    /**
     * @return array<int, string>
     */
    public function getMigrations(): array
    {
        return $this->migrations;
    }
}
