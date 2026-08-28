<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands\Concerns;

use Illuminate\Console\CommandMutex;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vherbaut\DataMigrations\Locking\DataMigrationsCommandMutex;

/**
 * Makes an Isolatable command use the lock shared by every data migration command.
 */
trait IsolatesDataMigrations
{
    /**
     * Turn the configured lock into the --isolated option before Laravel checks it.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('isolated') === false) {
            if (config('data-migrations.lock.enabled', false)) {
                $input->setOption('isolated', null);
            }
        }

        return parent::execute($input, $output);
    }

    /**
     * Get the mutex shared by every data migration command.
     *
     * @return CommandMutex
     */
    protected function commandIsolationMutex(): CommandMutex
    {
        /** @var string|null $store */
        $store = config('data-migrations.lock.store');

        return $this->laravel->make(DataMigrationsCommandMutex::class)->useStore($store);
    }

    /**
     * Get the moment after which a lock left behind by a killed process expires.
     *
     * @return Carbon
     */
    public function isolationLockExpiresAt(): Carbon
    {
        /** @var int $ttl */
        $ttl = config('data-migrations.lock.ttl', 3600);

        return Carbon::now()->addSeconds($ttl);
    }
}
