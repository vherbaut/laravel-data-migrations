<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidOptionException;

beforeEach(function (): void {
    $this->artisan('migrate');

    Schema::create('refresh_log', function (Blueprint $table): void {
        $table->id();
        $table->string('event');
    });
});

function refreshLogEvents(): array
{
    return DB::table('refresh_log')->orderBy('id')->pluck('event')->all();
}

it('rolls back the reversible migrations then runs every pending migration', function (): void {
    $reversible = $this->createTestMigration('refresh_a', dataMigrationContent(
        "DB::table('refresh_log')->insert(['event' => 'a_up']); \$this->affected(1);",
        '',
        "DB::table('refresh_log')->insert(['event' => 'a_down']);",
    ));
    $irreversible = $this->createTestMigration('refresh_b', dataMigrationContent(
        "DB::table('refresh_log')->insert(['event' => 'b_up']); \$this->affected(1);",
    ));
    $this->artisan('data:migrate', ['--force' => true]);
    $pending = $this->createTestMigration('refresh_c', dataMigrationContent(
        "DB::table('refresh_log')->insert(['event' => 'c_up']); \$this->affected(1);",
    ));

    $this->artisan('data:refresh', ['--force' => true])
        ->expectsOutputToContain('Rolling back:')
        ->expectsOutputToContain('Migrating:')
        ->assertSuccessful();

    $statuses = DB::table('data_migrations')->orderBy('migration')->get(['migration', 'status', 'batch'])
        ->map(fn (object $record): array => [$record->status, $record->batch])
        ->all();

    expect(refreshLogEvents())->toBe(['a_up', 'b_up', 'a_down', 'a_up', 'c_up'])
        ->and($statuses)->toBe([['completed', 2], ['completed', 1], ['completed', 2]]);
});

it('refuses to refresh while a migration is unresolved', function (): void {
    $failed = $this->createTestMigration('refresh_failed', dataMigrationContent('$this->affected(1);'));
    insertDataMigrationRecord(basename($failed, '.php'), 1, 'failed');

    $this->artisan('data:refresh', ['--force' => true])
        ->expectsOutputToContain('--retry-failed')
        ->assertFailed();

    expect(DB::table('data_migrations')->value('status'))->toBe('failed');
});

it('requires force flag in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('data:refresh')
        ->expectsConfirmation('You are about to roll back and run again ALL data migrations in production. Continue?', 'no')
        ->assertFailed();
});

it('fails when migrations table does not exist', function (): void {
    Schema::dropIfExists('data_migrations');

    $this->artisan('data:refresh', ['--force' => true])
        ->expectsOutputToContain('Data migrations table not found')
        ->assertFailed();
});

it('returns a failure exit code when a migration fails', function (): void {
    $this->createTestMigration('failing_refresh', dataMigrationContent("throw new RuntimeException('Boom');"));

    $this->artisan('data:refresh', ['--force' => true])
        ->assertFailed();

    $this->assertDatabaseHas('data_migrations', ['status' => 'failed']);
});

it('does not accept the --seed option', function (): void {
    expect(fn () => $this->artisan('data:refresh', ['--force' => true, '--seed' => true]))
        ->toThrow(InvalidOptionException::class);
});

it('no longer registers data:fresh', function (): void {
    expect(fn () => $this->artisan('data:fresh', ['--force' => true]))
        ->toThrow(CommandNotFoundException::class);
});
