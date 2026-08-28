<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Console\Kernel;

/**
 * Runs data:migrate once a schema migration command completed, when the run_after_migrate option is on.
 *
 * CommandFinished is used rather than MigrationsEnded because Laravel does not
 * dispatch the latter when no schema migration is pending, and because its
 * options are not available on every supported Laravel version.
 */
class RunDataMigrationsAfterMigrate
{
    /**
     * Schema migration commands followed by a data migration run.
     *
     * @var array<int, string>
     */
    protected const COMMANDS = ['migrate', 'migrate:fresh', 'migrate:refresh'];

    /**
     * Guards against reentrance while data:migrate runs.
     *
     * @var bool
     */
    protected static bool $running = false;

    /**
     * @param Kernel $artisan
     */
    public function __construct(
        protected Kernel $artisan,
    ) {}

    /**
     * @param CommandFinished $event
     * @return void
     */
    public function handle(CommandFinished $event): void
    {
        if (! $this->shouldRunAfter($event)) {
            return;
        }

        self::$running = true;

        try {
            $this->artisan->call('data:migrate', ['--force' => true], $event->output);
        } finally {
            self::$running = false;
        }
    }

    /**
     * @param CommandFinished $event
     * @return bool
     */
    protected function shouldRunAfter(CommandFinished $event): bool
    {
        if (self::$running) {
            return false;
        }

        if (! config('data-migrations.run_after_migrate', false)) {
            return false;
        }

        if (! in_array($event->command, self::COMMANDS, true)) {
            return false;
        }

        if ($event->exitCode !== 0) {
            return false;
        }

        if ($event->input->hasOption('pretend')) {
            if ($event->input->getOption('pretend')) {
                return false;
            }
        }

        return true;
    }
}
