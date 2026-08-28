<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands\Concerns;

use Illuminate\Console\Command;
use Throwable;
use Vherbaut\DataMigrations\Exceptions\MigrationException;

/**
 * Prints a failure the same way in every data migration command.
 */
trait ReportsMigrationFailures
{
    /**
     * @param Throwable $exception
     * @param string $label
     * @return int
     */
    protected function reportFailure(Throwable $exception, string $label): int
    {
        if ($exception instanceof MigrationException) {
            $this->error("{$label}: {$exception->getMessage()}");

            return Command::FAILURE;
        }

        $this->error("Unexpected error: {$exception->getMessage()}");

        if ($this->output->isVerbose()) {
            $this->line($exception->getTraceAsString());
        }

        return Command::FAILURE;
    }
}
