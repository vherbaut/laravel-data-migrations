<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Migration;

use Illuminate\Console\OutputStyle;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Vherbaut\DataMigrations\Concerns\TracksProgress;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;

/**
 * Abstract base class for data migrations.
 */
abstract class DataMigration implements MigrationInterface
{
    use TracksProgress;

    /**
     * The database connection to use.
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * Whether to wrap the migration in a transaction.
     *
     * @var bool
     */
    protected bool $withinTransaction = true;

    /**
     * Chunk size for processing large datasets.
     *
     * @var int
     */
    protected int $chunkSize = 1000;

    /**
     * Whether this migration is idempotent (safe to run multiple times).
     *
     * @var bool
     */
    protected bool $idempotent = false;

    /**
     * Maximum execution time in seconds (0 = use config default, null = unlimited).
     *
     * @var int|null
     */
    protected ?int $timeout = 0;

    /**
     * Description of what this migration does.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Tables affected by this migration (for documentation/safety).
     *
     * @var array<int, string>
     */
    protected array $affectedTables = [];

    /**
     * Console output instance.
     *
     * @var OutputStyle|null
     */
    protected ?OutputStyle $output = null;

    /**
     * Number of rows affected.
     *
     * @var int
     */
    protected int $rowsAffected = 0;

    /**
     * Run the data migration.
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the data migration.
     *
     * @return void
     */
    public function down(): void
    {
        // Optional: Override in migration if reversible
    }

    /**
     * Determine if this migration is reversible.
     *
     * @return bool
     */
    public function isReversible(): bool
    {
        $reflection = new ReflectionMethod($this, 'down');

        return $reflection->getDeclaringClass()->getName() !== self::class;
    }

    /**
     * Get the estimated number of rows that will be affected.
     * Used for confirmation and progress tracking.
     *
     * @return int|null
     */
    public function getEstimatedRows(): ?int
    {
        return null;
    }

    /**
     * Perform a dry run and return what would happen.
     *
     * @return array{
     *     description: string,
     *     affected_tables: array<int, string>,
     *     estimated_rows: int|null,
     *     reversible: bool,
     *     idempotent: bool,
     *     uses_transaction: bool
     * }
     */
    public function dryRun(): array
    {
        return [
            'description' => $this->getDescription(),
            'affected_tables' => $this->affectedTables,
            'estimated_rows' => $this->getEstimatedRows(),
            'reversible' => $this->isReversible(),
            'idempotent' => $this->idempotent,
            'uses_transaction' => $this->withinTransaction,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the database connection.
     *
     * @return Connection
     */
    protected function db(): Connection
    {
        return DB::connection($this->connection);
    }

    /**
     * Process records in chunks.
     *
     * @param string $table
     * @param callable(object): void $callback
     * @param int|null $chunkSize
     * @return int
     */
    protected function chunk(string $table, callable $callback, ?int $chunkSize = null): int
    {
        $processed = 0;
        $chunkSize = $chunkSize ?? $this->chunkSize;

        $this->db()->table($table)->orderBy('id')->chunk($chunkSize, function ($records) use ($callback, &$processed): bool {
            foreach ($records as $record) {
                $callback($record);
                $processed++;
                $this->incrementProgress();
            }

            return true;
        });

        return $processed;
    }

    /**
     * Process records in chunks using lazy collection (memory efficient).
     *
     * @param string $table
     * @param callable(object): void $callback
     * @param int|null $chunkSize
     * @return int
     */
    protected function chunkLazy(string $table, callable $callback, ?int $chunkSize = null): int
    {
        $processed = 0;
        $chunkSize = $chunkSize ?? $this->chunkSize;

        foreach ($this->db()->table($table)->orderBy('id')->lazy($chunkSize) as $record) {
            $callback($record);
            $processed++;
            $this->incrementProgress();
        }

        return $processed;
    }

    /**
     * Process with chunked updates (for mass updates).
     *
     * @param string $table
     * @param array<string, mixed> $updates
     * @param callable(\Illuminate\Database\Query\Builder): void $whereCallback
     * @param int|null $chunkSize
     * @return int
     */
    protected function chunkUpdate(string $table, array $updates, callable $whereCallback, ?int $chunkSize = null): int
    {
        $totalAffected = 0;
        $chunkSize = $chunkSize ?? $this->chunkSize;

        do {
            $query = $this->db()->table($table);
            $whereCallback($query);

            $affected = $query->limit($chunkSize)->update($updates);
            $totalAffected += $affected;

            $this->addProgress($affected);
        } while ($affected > 0);

        return $totalAffected;
    }

    /**
     * Log a message.
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if (config('data-migrations.logging.enabled')) {
            Log::channel(config('data-migrations.logging.channel'))->$level("[DataMigration] {$message}");
        }

        $this->info($message);
    }

    /**
     * Output info message.
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        if ($this->output !== null) {
            $this->output->info($message);
        }
    }

    /**
     * Output warning message.
     *
     * @param string $message
     * @return void
     */
    protected function warn(string $message): void
    {
        if ($this->output !== null) {
            $this->output->warning($message);
        }
    }

    /**
     * Output error message.
     *
     * @param string $message
     * @return void
     */
    protected function error(string $message): void
    {
        if ($this->output !== null) {
            $this->output->error($message);
        }
    }

    /**
     * Increment the rows affected counter.
     *
     * @param int $count
     * @return void
     */
    protected function affected(int $count = 1): void
    {
        $this->rowsAffected += $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters / Setters
    |--------------------------------------------------------------------------
    */

    /**
     * Get the human-readable description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the list of affected tables.
     *
     * @return array<int, string>
     */
    public function getAffectedTables(): array
    {
        return $this->affectedTables;
    }

    /**
     * Whether this migration should run within a transaction.
     *
     * @return bool
     */
    public function shouldRunInTransaction(): bool
    {
        return $this->withinTransaction;
    }

    /**
     * Whether this migration is idempotent.
     *
     * @return bool
     */
    public function isIdempotent(): bool
    {
        return $this->idempotent;
    }

    /**
     * Get the number of rows affected after migration.
     *
     * @return int
     */
    public function getRowsAffected(): int
    {
        return $this->rowsAffected;
    }

    /**
     * Set the console output instance.
     *
     * @param OutputStyle $output
     * @return static
     */
    public function setOutput(OutputStyle $output): static
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get the database connection name.
     *
     * @return string|null
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Get the timeout for this migration.
     *
     * @return int|null
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }
}
