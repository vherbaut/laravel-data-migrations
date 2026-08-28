<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->artisan('migrate');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('refuses to run data:migrate --isolated while the lock is held', function (): void {
    $this->createTestMigration('isolated_blocked', dataMigrationContent('$this->affected(1);'));
    Cache::lock('data-migrations')->get();

    $this->artisan('data:migrate', ['--force' => true, '--isolated' => true])
        ->expectsOutputToContain('already running')
        ->assertSuccessful();

    expect(DB::table('data_migrations')->count())->toBe(0);
});

it('returns the given exit code with --isolated=3', function (): void {
    Cache::lock('data-migrations')->get();

    $this->artisan('data:migrate', ['--force' => true, '--isolated' => 3])
        ->expectsOutputToContain('already running')
        ->assertExitCode(3);
});

it('runs data:migrate without --isolated even if the lock is held', function (): void {
    $this->createTestMigration('isolated_default', dataMigrationContent('$this->affected(1);'));
    Cache::lock('data-migrations')->get();

    $this->artisan('data:migrate', ['--force' => true])->assertSuccessful();

    expect(DB::table('data_migrations')->count())->toBe(1);
});

it('refuses to run when lock.enabled is set without the option', function (): void {
    config()->set('data-migrations.lock.enabled', true);
    $this->createTestMigration('isolated_by_config', dataMigrationContent('$this->affected(1);'));
    Cache::lock('data-migrations')->get();

    $this->artisan('data:migrate', ['--force' => true])
        ->expectsOutputToContain('already running')
        ->assertSuccessful();

    expect(DB::table('data_migrations')->count())->toBe(0);
});

it('releases the lock after success', function (): void {
    $this->createTestMigration('isolated_release', dataMigrationContent('$this->affected(1);'));

    $this->artisan('data:migrate', ['--force' => true, '--isolated' => true])->assertSuccessful();

    expect(DB::table('data_migrations')->count())->toBe(1)
        ->and(Cache::lock('data-migrations')->get())->toBeTrue();
});

it('releases the lock after a failing migration', function (): void {
    $this->createTestMigration('isolated_failure', dataMigrationContent("throw new RuntimeException('boom');"));

    $this->artisan('data:migrate', ['--force' => true, '--isolated' => true])->assertFailed();

    expect(Cache::lock('data-migrations')->get())->toBeTrue();
});

it('shares the lock between data:migrate, data:rollback and data:refresh', function (): void {
    Cache::lock('data-migrations')->get();

    $this->artisan('data:rollback', ['--force' => true, '--isolated' => true])
        ->expectsOutputToContain('already running')
        ->assertSuccessful();

    $this->artisan('data:refresh', ['--force' => true, '--isolated' => true])
        ->expectsOutputToContain('already running')
        ->assertSuccessful();
});

it('runs data:refresh --isolated when lock.enabled is set', function (): void {
    config()->set('data-migrations.lock.enabled', true);
    $this->createTestMigration('isolated_refresh', dataMigrationContent('$this->affected(1);'));

    $this->artisan('data:refresh', ['--force' => true, '--isolated' => true])->assertSuccessful();

    expect(DB::table('data_migrations')->where('status', 'completed')->count())->toBe(1)
        ->and(Cache::lock('data-migrations')->get())->toBeTrue();
});

it('uses the configured cache store', function (): void {
    config()->set('cache.stores.other', ['driver' => 'array']);
    config()->set('data-migrations.lock.store', 'other');
    $this->createTestMigration('isolated_store', dataMigrationContent('$this->affected(1);'));
    Cache::lock('data-migrations')->get();

    $this->artisan('data:migrate', ['--force' => true, '--isolated' => true])->assertSuccessful();

    expect(DB::table('data_migrations')->count())->toBe(1);

    Cache::store('other')->lock('data-migrations')->get();

    $this->artisan('data:rollback', ['--force' => true, '--isolated' => true])
        ->expectsOutputToContain('already running')
        ->assertSuccessful();
});

it('expires the lock after the configured ttl', function (): void {
    config()->set('data-migrations.lock.ttl', 5);
    $this->createTestMigration('isolated_ttl', dataMigrationContent(<<<'PHP'
        Illuminate\Support\Carbon::setTestNow(now()->addSeconds(6));
        $this->affected(Illuminate\Support\Facades\Cache::lock('data-migrations')->get() ? 1 : 0);
    PHP));

    $this->artisan('data:migrate', ['--force' => true, '--isolated' => true])->assertSuccessful();

    expect(DB::table('data_migrations')->value('rows_affected'))->toBe(1);
});
