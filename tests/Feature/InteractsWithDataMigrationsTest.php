<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\ExpectationFailedException;
use Vherbaut\DataMigrations\Exceptions\MigrationNotFoundException;
use Vherbaut\DataMigrations\Testing\InteractsWithDataMigrations;

uses(InteractsWithDataMigrations::class);

beforeEach(function (): void {
    $this->artisan('migrate');
});

it('runs a single migration by name in its own batch', function (): void {
    insertDataMigrationRecord('earlier', 1, 'completed');
    $file = $this->createTestMigration('single', dataMigrationContent('$this->affected(2);'));
    $name = basename($file, '.php');

    $this->runDataMigration($name);

    $record = DB::table('data_migrations')->where('migration', $name)->first();

    expect($record->status)->toBe('completed')
        ->and($record->batch)->toBe(2)
        ->and($record->rows_affected)->toBe(2);
});

it('throws when the migration does not exist', function (): void {
    expect(fn () => $this->runDataMigration('missing'))->toThrow(MigrationNotFoundException::class);
});

it('throws when the migration already ran', function (): void {
    $file = $this->createTestMigration('twice', dataMigrationContent());
    $name = basename($file, '.php');
    $this->runDataMigration($name);

    expect(fn () => $this->runDataMigration($name))->toThrow(LogicException::class);
});

it('runs all pending migrations', function (): void {
    $file = $this->createTestMigration('all_pending', dataMigrationContent());

    expect($this->runDataMigrations())->toBe([$file])
        ->and(DB::table('data_migrations')->where('status', 'completed')->count())->toBe(1);
});

it('rolls back a migration run on its own batch', function (): void {
    $file = $this->createTestMigration('reversible', dataMigrationContent('', '', '$this->affected(1);'));
    $name = basename($file, '.php');
    $this->runDataMigration($name);

    $this->rollbackDataMigration($name);

    expect(DB::table('data_migrations')->where('migration', $name)->value('status'))->toBe('rolled_back');
});

it('refuses to roll back a migration that shares its batch', function (): void {
    insertDataMigrationRecord('sibling', 1, 'completed');
    $file = $this->createTestMigration('shared', dataMigrationContent('', '', '$this->affected(1);'));
    $name = basename($file, '.php');
    insertDataMigrationRecord($name, 1, 'completed');

    expect(fn () => $this->rollbackDataMigration($name))->toThrow(LogicException::class)
        ->and(DB::table('data_migrations')->where('status', 'completed')->count())->toBe(2);
});

it('throws when rolling back a migration that never ran', function (): void {
    expect(fn () => $this->rollbackDataMigration('never_ran'))->toThrow(LogicException::class);
});

it('asserts the ran status', function (): void {
    $file = $this->createTestMigration('asserted', dataMigrationContent());
    $name = basename($file, '.php');

    $this->assertDataMigrationNotRan($name);
    expect(fn () => $this->assertDataMigrationRan($name))->toThrow(ExpectationFailedException::class);

    $this->runDataMigration($name);

    $this->assertDataMigrationRan($name);
    expect(fn () => $this->assertDataMigrationNotRan($name))->toThrow(ExpectationFailedException::class);
});

it('asserts the failed status', function (): void {
    $file = $this->createTestMigration('failing', dataMigrationContent("throw new RuntimeException('boom');"));
    $name = basename($file, '.php');

    expect(fn () => $this->runDataMigration($name))->toThrow(RuntimeException::class);

    $this->assertDataMigrationFailed($name);
    $this->assertDataMigrationNotRan($name);
    expect(fn () => $this->assertDataMigrationFailed('other'))->toThrow(ExpectationFailedException::class);
});

it('asserts the rolled back status', function (): void {
    $file = $this->createTestMigration('reverted', dataMigrationContent('', '', '$this->affected(1);'));
    $name = basename($file, '.php');
    $this->runDataMigration($name);

    expect(fn () => $this->assertDataMigrationRolledBack($name))->toThrow(ExpectationFailedException::class);

    $this->rollbackDataMigration($name);

    $this->assertDataMigrationRolledBack($name);
    $this->assertDataMigrationNotRan($name);
});
