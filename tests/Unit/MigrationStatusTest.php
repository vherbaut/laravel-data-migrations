<?php

declare(strict_types=1);

use Vherbaut\DataMigrations\Enums\MigrationStatus;

it('treats failed and running as unresolved', function (): void {
    expect(MigrationStatus::Failed->isUnresolved())->toBeTrue()
        ->and(MigrationStatus::Running->isUnresolved())->toBeTrue()
        ->and(MigrationStatus::Completed->isUnresolved())->toBeFalse()
        ->and(MigrationStatus::RolledBack->isUnresolved())->toBeFalse()
        ->and(MigrationStatus::Pending->isUnresolved())->toBeFalse();
});

it('maps the persisted values', function (): void {
    expect(MigrationStatus::from('rolled_back'))->toBe(MigrationStatus::RolledBack)
        ->and(MigrationStatus::Completed->value)->toBe('completed');
});
