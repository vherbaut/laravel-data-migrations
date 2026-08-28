<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Migration\MigrationRepository;

beforeEach(function (): void {
    $this->artisan('migrate');
    $this->repository = app(MigrationRepositoryInterface::class);
});

it('returns only completed records from getRollbackable', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_running', 2, 'running');
    insertDataMigrationRecord('c_failed', 3, 'failed');
    insertDataMigrationRecord('d_rolled_back', 4, 'rolled_back');

    expect($this->repository->getRollbackable()->pluck('migration')->all())
        ->toBe(['a_completed']);
});

it('applies the steps limit after filtering rollbackable records', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_running', 2, 'running');
    insertDataMigrationRecord('c_failed', 3, 'failed');
    insertDataMigrationRecord('d_rolled_back', 4, 'rolled_back');

    expect($this->repository->getRollbackable(1)->pluck('migration')->all())
        ->toBe(['a_completed']);
});

it('returns only completed records from getRollbackableByBatch', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_rolled_back', 1, 'rolled_back');
    insertDataMigrationRecord('c_failed', 1, 'failed');
    insertDataMigrationRecord('d_running', 1, 'running');

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

it('records a running migration on logStart', function (): void {
    $this->repository->logStart('fresh_migration', 3);

    $record = DB::table('data_migrations')->first();

    expect($record->migration)->toBe('fresh_migration')
        ->and($record->status)->toBe('running')
        ->and($record->batch)->toBe(3)
        ->and($record->started_at)->not->toBeNull();
});

it('replaces any previous record of the migration on logStart', function (): void {
    insertDataMigrationRecord('retried_failed', 1, 'failed');
    insertDataMigrationRecord('retried_rolled_back', 1, 'rolled_back');
    insertDataMigrationRecord('retried_running', 1, 'running');

    $this->repository->logStart('retried_failed', 2);
    $this->repository->logStart('retried_rolled_back', 2);
    $this->repository->logStart('retried_running', 2);

    expect(DB::table('data_migrations')->count())->toBe(3)
        ->and(DB::table('data_migrations')->pluck('status')->unique()->all())->toBe(['running'])
        ->and(DB::table('data_migrations')->pluck('batch')->unique()->all())->toBe([2]);
});

it('marks a record as completed with rows duration and metadata', function (): void {
    $this->repository->logStart('completing', 1);
    $this->repository->logFailed('completing', 'previous error');

    $this->repository->logComplete('completing', 12, 340, ['description' => 'Backfill']);

    $record = DB::table('data_migrations')->first();

    expect($record->status)->toBe('completed')
        ->and($record->rows_affected)->toBe(12)
        ->and($record->duration_ms)->toBe(340)
        ->and(json_decode((string) $record->metadata, true))->toBe(['description' => 'Backfill'])
        ->and($record->completed_at)->not->toBeNull()
        ->and($record->error_message)->toBeNull();
});

it('marks a record as failed with the error message', function (): void {
    $this->repository->logStart('failing', 1);

    $this->repository->logFailed('failing', 'Something broke');

    $record = DB::table('data_migrations')->first();

    expect($record->status)->toBe('failed')
        ->and($record->error_message)->toBe('Something broke');
});

it('marks a record as rolled back', function (): void {
    insertDataMigrationRecord('reverting', 1, 'completed');

    $this->repository->logRollback('reverting');

    expect(DB::table('data_migrations')->value('status'))->toBe('rolled_back');
});

it('computes the next batch number from every record regardless of status', function (): void {
    expect($this->repository->getNextBatchNumber())->toBe(1);

    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_failed', 4, 'failed');

    expect($this->repository->getLastBatchNumber())->toBe(4)
        ->and($this->repository->getNextBatchNumber())->toBe(5);
});

it('reports hasRun only for completed records', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_running', 1, 'running');
    insertDataMigrationRecord('c_failed', 1, 'failed');
    insertDataMigrationRecord('d_rolled_back', 1, 'rolled_back');

    expect($this->repository->hasRun('a_completed'))->toBeTrue()
        ->and($this->repository->hasRun('b_running'))->toBeFalse()
        ->and($this->repository->hasRun('c_failed'))->toBeFalse()
        ->and($this->repository->hasRun('d_rolled_back'))->toBeFalse()
        ->and($this->repository->hasRun('unknown'))->toBeFalse();
});

it('returns a record from getMigration and null for unknown names', function (): void {
    insertDataMigrationRecord('known', 2, 'completed');

    $record = $this->repository->getMigration('known');

    expect($record)->not->toBeNull()
        ->and($record->migration)->toBe('known')
        ->and($record->batch)->toBe(2)
        ->and($record->isCompleted())->toBeTrue()
        ->and($this->repository->getMigration('unknown'))->toBeNull();
});

it('deletes a record by name', function (): void {
    insertDataMigrationRecord('kept', 1, 'completed');
    insertDataMigrationRecord('removed', 1, 'completed');

    $this->repository->delete('removed');

    expect(DB::table('data_migrations')->pluck('migration')->all())->toBe(['kept']);
});

it('returns completed migration names ordered by batch', function (): void {
    insertDataMigrationRecord('z_first_batch', 1, 'completed');
    insertDataMigrationRecord('a_second_batch', 2, 'completed');
    insertDataMigrationRecord('b_running', 3, 'running');
    insertDataMigrationRecord('m_failed', 4, 'failed');

    expect($this->repository->getRan())->toBe(['z_first_batch', 'a_second_batch']);
});

it('returns failed and running records from getUnresolved ordered by batch and name', function (): void {
    insertDataMigrationRecord('z_running', 1, 'running');
    insertDataMigrationRecord('a_completed', 2, 'completed');
    insertDataMigrationRecord('b_failed', 2, 'failed');
    insertDataMigrationRecord('c_rolled_back', 3, 'rolled_back');

    expect($this->repository->getUnresolved()->pluck('migration')->all())->toBe(['z_running', 'b_failed']);
});

it('ignores running records when looking for the last batch', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_running', 2, 'running');

    expect($this->repository->getLast()->pluck('migration')->all())->toBe(['a_completed']);
});

it('uses the connection given to the constructor', function (): void {
    config()->set('database.connections.other', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    $default = new MigrationRepository(app('db'), 'data_migrations');
    $other = new MigrationRepository(app('db'), 'data_migrations', 'other');

    expect($default->repositoryExists())->toBeTrue()
        ->and($other->repositoryExists())->toBeFalse();
});
