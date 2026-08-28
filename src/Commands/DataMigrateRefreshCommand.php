<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;
use Vherbaut\DataMigrations\Commands\Concerns\IsolatesDataMigrations;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Exceptions\MigrationException;

/**
 * Roll back every completed data migration that can be reverted, then run
 * the pending migrations again.
 */
class DataMigrateRefreshCommand extends Command implements Isolatable
{
    use ConfirmableTrait;
    use IsolatesDataMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:refresh
                            {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Roll back every data migration then run them again';

    /**
     * @var MigratorInterface
     */
    protected MigratorInterface $migrator;

    /**
     * @param MigratorInterface $migrator
     */
    public function __construct(MigratorInterface $migrator)
    {
        parent::__construct();
        $this->migrator = $migrator;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->migrator->setOutput($this->output);

        if (! $this->migrator->getRepository()->repositoryExists()) {
            $this->error('Data migrations table not found. Run: php artisan migrate');

            return self::FAILURE;
        }

        try {
            $this->migrator->rollback(['all' => true]);
            $this->migrator->run();

            return self::SUCCESS;
        } catch (MigrationException $exception) {
            $this->error("Migration error: {$exception->getMessage()}");

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error("Unexpected error: {$exception->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->line($exception->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Confirm before running in production, unless --force is given.
     *
     * @return bool
     */
    protected function confirmToProceed(): bool
    {
        /** @var bool $shouldConfirm */
        $shouldConfirm = config('data-migrations.safety.require_force_in_production', true);

        if (! $shouldConfirm) {
            return true;
        }

        if (! app()->environment('production')) {
            return true;
        }

        if ((bool) $this->option('force')) {
            return true;
        }

        return $this->confirm('You are about to roll back and run again ALL data migrations in production. Continue?');
    }
}
