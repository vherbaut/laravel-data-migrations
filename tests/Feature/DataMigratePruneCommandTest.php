<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->artisan('migrate');
});

it('reports no orphaned records', function (): void {
    $this->createTestMigration('present', dataMigrationContent());
    $this->artisan('data:migrate', ['--force' => true]);

    $this->artisan('data:prune')
        ->expectsOutputToContain('No orphaned records found.')
        ->assertSuccessful();

    expect(DB::table('data_migrations')->count())->toBe(1);
});

it('prunes orphaned records and keeps records with files', function (): void {
    $file = $this->createTestMigration('present', dataMigrationContent());
    $this->artisan('data:migrate', ['--force' => true]);
    insertDataMigrationRecord('vanished_one', 2, 'completed');
    insertDataMigrationRecord('vanished_two', 3, 'failed');

    $this->artisan('data:prune')
        ->expectsOutputToContain('vanished_one')
        ->expectsOutputToContain('vanished_two')
        ->expectsOutputToContain('2 orphaned record(s) pruned.')
        ->assertSuccessful();

    expect(DB::table('data_migrations')->pluck('migration')->all())->toBe([basename($file, '.php')]);
});

it('refuses to prune when no migration files exist at all', function (): void {
    insertDataMigrationRecord('vanished', 1, 'completed');

    $this->artisan('data:prune')
        ->expectsOutputToContain('No data migration files found')
        ->assertFailed();

    expect(DB::table('data_migrations')->count())->toBe(1);
});

it('requires force flag in production', function (): void {
    app()->detectEnvironment(fn () => 'production');
    insertDataMigrationRecord('vanished', 1, 'completed');

    $this->artisan('data:prune')
        ->expectsConfirmation('You are about to delete orphaned data migration records in production. Continue?', 'no')
        ->assertFailed();

    expect(DB::table('data_migrations')->count())->toBe(1);
});

it('fails when migrations table does not exist', function (): void {
    Schema::dropIfExists('data_migrations');

    $this->artisan('data:prune', ['--force' => true])
        ->expectsOutputToContain('Data migrations table not found')
        ->assertFailed();
});
