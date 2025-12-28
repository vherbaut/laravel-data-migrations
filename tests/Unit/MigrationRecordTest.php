<?php

declare(strict_types=1);

use Vherbaut\DataMigrations\DTO\MigrationRecord;

it('creates a migration record from database object', function (): void {
    $record = (object) [
        'id' => 1,
        'migration' => '2024_01_01_000000_test_migration',
        'batch' => 1,
        'status' => 'completed',
        'rows_affected' => 100,
        'duration_ms' => 500,
        'error_message' => null,
        'metadata' => '{"description":"Test"}',
        'started_at' => '2024-01-01 10:00:00',
        'completed_at' => '2024-01-01 10:00:05',
        'created_at' => '2024-01-01 10:00:00',
        'updated_at' => '2024-01-01 10:00:05',
    ];

    $migrationRecord = MigrationRecord::fromDatabaseRecord($record);

    expect($migrationRecord->id)->toBe(1)
        ->and($migrationRecord->migration)->toBe('2024_01_01_000000_test_migration')
        ->and($migrationRecord->batch)->toBe(1)
        ->and($migrationRecord->status)->toBe('completed')
        ->and($migrationRecord->rowsAffected)->toBe(100)
        ->and($migrationRecord->durationMs)->toBe(500)
        ->and($migrationRecord->errorMessage)->toBeNull()
        ->and($migrationRecord->metadata)->toBe(['description' => 'Test']);
});

it('handles null optional fields', function (): void {
    $record = (object) [
        'id' => 1,
        'migration' => '2024_01_01_000000_test_migration',
        'batch' => 1,
        'status' => 'running',
        'created_at' => '2024-01-01 10:00:00',
        'updated_at' => '2024-01-01 10:00:00',
    ];

    $migrationRecord = MigrationRecord::fromDatabaseRecord($record);

    expect($migrationRecord->rowsAffected)->toBeNull()
        ->and($migrationRecord->durationMs)->toBeNull()
        ->and($migrationRecord->completedAt)->toBeNull();
});

it('detects completed status', function (): void {
    $record = (object) [
        'id' => 1,
        'migration' => 'test',
        'batch' => 1,
        'status' => 'completed',
        'created_at' => '2024-01-01 10:00:00',
        'updated_at' => '2024-01-01 10:00:00',
    ];

    $migrationRecord = MigrationRecord::fromDatabaseRecord($record);

    expect($migrationRecord->isCompleted())->toBeTrue()
        ->and($migrationRecord->isPending())->toBeFalse()
        ->and($migrationRecord->isRunning())->toBeFalse()
        ->and($migrationRecord->isFailed())->toBeFalse();
});

it('detects failed status', function (): void {
    $record = (object) [
        'id' => 1,
        'migration' => 'test',
        'batch' => 1,
        'status' => 'failed',
        'error_message' => 'Something went wrong',
        'created_at' => '2024-01-01 10:00:00',
        'updated_at' => '2024-01-01 10:00:00',
    ];

    $migrationRecord = MigrationRecord::fromDatabaseRecord($record);

    expect($migrationRecord->isFailed())->toBeTrue()
        ->and($migrationRecord->errorMessage)->toBe('Something went wrong');
});

it('detects rolled back status', function (): void {
    $record = (object) [
        'id' => 1,
        'migration' => 'test',
        'batch' => 1,
        'status' => 'rolled_back',
        'created_at' => '2024-01-01 10:00:00',
        'updated_at' => '2024-01-01 10:00:00',
    ];

    $migrationRecord = MigrationRecord::fromDatabaseRecord($record);

    expect($migrationRecord->isRolledBack())->toBeTrue();
});
