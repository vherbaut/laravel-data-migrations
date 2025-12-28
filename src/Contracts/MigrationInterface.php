<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

use Illuminate\Console\OutputStyle;

/**
 * Contract for data migrations.
 */
interface MigrationInterface
{
    /**
     * Run the data migration.
     *
     * @return void
     */
    public function up(): void;

    /**
     * Reverse the data migration.
     *
     * @return void
     */
    public function down(): void;

    /**
     * Determine if this migration is reversible.
     *
     * @return bool
     */
    public function isReversible(): bool;

    /**
     * Get the estimated number of rows that will be affected.
     *
     * @return int|null
     */
    public function getEstimatedRows(): ?int;

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
    public function dryRun(): array;

    /**
     * Get the human-readable description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get the list of affected tables.
     *
     * @return array<int, string>
     */
    public function getAffectedTables(): array;

    /**
     * Whether this migration should run within a transaction.
     *
     * @return bool
     */
    public function shouldRunInTransaction(): bool;

    /**
     * Whether this migration is idempotent.
     *
     * @return bool
     */
    public function isIdempotent(): bool;

    /**
     * Get the number of rows affected after migration.
     *
     * @return int
     */
    public function getRowsAffected(): int;

    /**
     * Set the console output instance.
     *
     * @param OutputStyle $output
     * @return static
     */
    public function setOutput(OutputStyle $output): static;

    /**
     * Get the database connection name.
     *
     * @return string|null
     */
    public function getConnection(): ?string;

    /**
     * Get the timeout for this migration.
     *
     * @return int|null
     */
    public function getTimeout(): ?int;
}
