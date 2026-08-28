<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\ExpectationFailedException;
use Vherbaut\DataMigrations\Facades\DataMigrations;
use Vherbaut\DataMigrations\Testing\MigratorFake;

beforeEach(function (): void {
    $this->artisan('migrate');
});

it('keeps commands from executing migrations even when artisan was already booted', function (): void {
    $file = $this->createTestMigration('faked', dataMigrationContent("throw new RuntimeException('should not run');"));
    $fake = DataMigrations::fake();

    $this->artisan('data:migrate', ['--force' => true])->assertSuccessful();

    expect($fake)->toBeInstanceOf(MigratorFake::class)
        ->and(DB::table('data_migrations')->count())->toBe(0);
    $fake->assertRan(basename($file, '.php'));
});

it('returns the pending files from run without executing them', function (): void {
    $file = $this->createTestMigration('listed', dataMigrationContent("throw new RuntimeException('should not run');"));
    DataMigrations::fake();

    expect(DataMigrations::run())->toBe([$file])
        ->and(DB::table('data_migrations')->count())->toBe(0);
});

it('asserts a migration was ran', function (): void {
    $file = $this->createTestMigration('ran', dataMigrationContent());
    $fake = DataMigrations::fake();

    DataMigrations::run();

    $fake->assertRan(basename($file, '.php'));
    expect(fn () => $fake->assertNotRan(basename($file, '.php')))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertNothingRan())->toThrow(ExpectationFailedException::class);
});

it('asserts a migration was not ran', function (): void {
    $file = $this->createTestMigration('pending', dataMigrationContent());
    $fake = DataMigrations::fake();

    $fake->assertNotRan(basename($file, '.php'));
    $fake->assertNothingRan();
    expect(fn () => $fake->assertRan(basename($file, '.php')))->toThrow(ExpectationFailedException::class);
});

it('records a single migration file without executing it', function (): void {
    $file = $this->createTestMigration('single', dataMigrationContent("throw new RuntimeException('should not run');"));
    $fake = DataMigrations::fake();

    DataMigrations::runMigration($file, 1);

    $fake->assertRan(basename($file, '.php'));
    expect(DB::table('data_migrations')->count())->toBe(0);
});

it('records nothing during a dry run', function (): void {
    $this->createTestMigration('dry', dataMigrationContent());
    $fake = DataMigrations::fake();

    DataMigrations::run(['dry-run' => true]);

    $fake->assertNothingRan();
});

it('records rollback targets without executing them', function (): void {
    $file = $this->createTestMigration('reverted', dataMigrationContent('', '', "throw new RuntimeException('should not run');"));
    $name = basename($file, '.php');
    insertDataMigrationRecord($name, 1, 'completed');
    $fake = DataMigrations::fake();

    expect(DataMigrations::rollback())->toBe([$file])
        ->and(DB::table('data_migrations')->value('status'))->toBe('completed');
    $fake->assertRolledBack($name);
    expect(fn () => $fake->assertNothingRolledBack())->toThrow(ExpectationFailedException::class);
});

it('skips non reversible migrations on rollback like the real migrator', function (): void {
    $file = $this->createTestMigration('not_reversible', dataMigrationContent());
    $name = basename($file, '.php');
    insertDataMigrationRecord($name, 1, 'completed');
    $fake = DataMigrations::fake();

    expect(DataMigrations::rollback())->toBe([]);
    $fake->assertNothingRolledBack();
    expect(fn () => $fake->assertRolledBack($name))->toThrow(ExpectationFailedException::class);
});
