<?php

declare(strict_types=1);

use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;

beforeEach(function (): void {
    $this->artisan('migrate');
    $this->repository = app(MigrationRepositoryInterface::class);
});

it('returns only completed and running records from getRollbackable', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_running', 2, 'running');
    insertDataMigrationRecord('c_failed', 3, 'failed');
    insertDataMigrationRecord('d_rolled_back', 4, 'rolled_back');

    expect($this->repository->getRollbackable()->pluck('migration')->all())
        ->toBe(['b_running', 'a_completed']);
});

it('applies the steps limit after filtering rollbackable records', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_running', 2, 'running');
    insertDataMigrationRecord('c_failed', 3, 'failed');
    insertDataMigrationRecord('d_rolled_back', 4, 'rolled_back');

    expect($this->repository->getRollbackable(1)->pluck('migration')->all())
        ->toBe(['b_running']);
});

it('returns only completed and running records from getRollbackableByBatch', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_rolled_back', 1, 'rolled_back');
    insertDataMigrationRecord('c_failed', 1, 'failed');

    expect($this->repository->getRollbackableByBatch(1)->pluck('migration')->all())
        ->toBe(['a_completed']);
});

it('keeps returning every status from getMigrations and getMigrationsByBatch', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_rolled_back', 1, 'rolled_back');
    insertDataMigrationRecord('c_failed', 1, 'failed');
    insertDataMigrationRecord('d_running', 2, 'running');

    expect($this->repository->getMigrations())->toHaveCount(4)
        ->and($this->repository->getMigrationsByBatch(1))->toHaveCount(3);
});
