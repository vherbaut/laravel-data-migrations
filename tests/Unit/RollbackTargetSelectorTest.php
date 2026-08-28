<?php

declare(strict_types=1);

use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Migration\RollbackTargetSelector;

beforeEach(function (): void {
    $this->artisan('migrate');
    $this->selector = new RollbackTargetSelector(app(MigrationRepositoryInterface::class));
});

it('selects the rollbackable records of a given batch', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_completed', 2, 'completed');
    insertDataMigrationRecord('c_failed', 2, 'failed');

    expect($this->selector->select(['batch' => '2'])->pluck('migration')->all())->toBe(['b_completed']);
});

it('selects the last rollbackable records when steps are given', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_completed', 2, 'completed');
    insertDataMigrationRecord('c_completed', 3, 'completed');
    insertDataMigrationRecord('d_rolled_back', 4, 'rolled_back');

    expect($this->selector->select(['step' => 2])->pluck('migration')->all())->toBe(['c_completed', 'b_completed']);
});

it('selects the last batch by default', function (): void {
    insertDataMigrationRecord('a_completed', 1, 'completed');
    insertDataMigrationRecord('b_completed', 2, 'completed');
    insertDataMigrationRecord('c_completed', 2, 'completed');

    expect($this->selector->select([])->pluck('migration')->all())->toBe(['c_completed', 'b_completed'])
        ->and($this->selector->select(['step' => 0])->pluck('migration')->all())->toBe(['c_completed', 'b_completed']);
});

it('selects nothing when there is nothing to roll back', function (): void {
    insertDataMigrationRecord('a_rolled_back', 1, 'rolled_back');

    expect($this->selector->select([]))->toBeEmpty()
        ->and($this->selector->select(['batch' => 1]))->toBeEmpty()
        ->and($this->selector->select(['step' => 3]))->toBeEmpty();
});
