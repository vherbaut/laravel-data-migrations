<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;
use Vherbaut\DataMigrations\Commands\Concerns\ConfirmsProductionRun;
use Vherbaut\DataMigrations\Commands\Concerns\IsolatesDataMigrations;
use Vherbaut\DataMigrations\Commands\Concerns\ReportsMigrationFailures;
use Vherbaut\DataMigrations\Contracts\MigratorInterface;
use Vherbaut\DataMigrations\Output\ConsoleOutput;

/**
 * Roll back every completed data migration that can be reverted, then run
 * the pending migrations again.
 */
class DataMigrateRefreshCommand extends Command implements Isolatable
{
    use ConfirmsProductionRun;
    use IsolatesDataMigrations;
    use ReportsMigrationFailures;

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
        if (! $this->confirmToProceed('You are about to roll back and run again every data migration in production.')) {
            return self::FAILURE;
        }

        $this->migrator->setOutput(new ConsoleOutput($this->output));

        if (! $this->migrator->getRepository()->repositoryExists()) {
            $this->error('Data migrations table not found. Run: php artisan migrate');

            return self::FAILURE;
        }

        try {
            $this->migrator->rollback(['all' => true]);
            $this->migrator->run();

            return self::SUCCESS;
        } catch (Throwable $exception) {
            return $this->reportFailure($exception, 'Refresh error');
        }
    }
}
