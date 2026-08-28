<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationInterface;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Events\DataMigrationEnded;
use Vherbaut\DataMigrations\Events\DataMigrationFailed;
use Vherbaut\DataMigrations\Events\DataMigrationStarted;
use Vherbaut\DataMigrations\Events\NoPendingDataMigrations;
use Vherbaut\DataMigrations\Migration\Migrator;
use Vherbaut\DataMigrations\Services\NullBackupService;

beforeEach(function (): void {
    $this->artisan('migrate');

    Schema::create('widgets', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    Event::fake();

    $this->migrator = new Migrator(
        app(MigrationRepositoryInterface::class),
        app('db'),
        app(MigrationFileResolverInterface::class),
        new NullBackupService,
        app('events'),
    );
});

it('dispatches started and ended events around a migration', function (): void {
    $file = $this->createTestMigration('evented', dataMigrationContent('$this->affected(3);'));
    $name = $this->migrator->getMigrationName($file);

    $this->migrator->run();

    Event::assertDispatched(DataMigrationStarted::class, fn (DataMigrationStarted $event): bool => $event->name === $name
        && $event->method === 'up'
        && $event->migration instanceof MigrationInterface);
    Event::assertDispatched(DataMigrationEnded::class, fn (DataMigrationEnded $event): bool => $event->name === $name
        && $event->method === 'up'
        && $event->rowsAffected === 3
        && $event->durationMs >= 0);
    Event::assertNotDispatched(DataMigrationFailed::class);
});

it('dispatches a failed event and rethrows', function (): void {
    $file = $this->createTestMigration('evented_failure', dataMigrationContent("throw new RuntimeException('boom');"));
    $name = $this->migrator->getMigrationName($file);

    expect(fn () => $this->migrator->run())->toThrow(RuntimeException::class, 'boom');

    Event::assertDispatched(DataMigrationStarted::class);
    Event::assertDispatched(DataMigrationFailed::class, fn (DataMigrationFailed $event): bool => $event->name === $name
        && $event->method === 'up'
        && $event->exception->getMessage() === 'boom');
    Event::assertNotDispatched(DataMigrationEnded::class);
});

it('dispatches events with method down on rollback', function (): void {
    $file = $this->createTestMigration('evented_rollback', dataMigrationContent('', '', '$this->affected(2);'));
    $name = $this->migrator->getMigrationName($file);
    insertDataMigrationRecord($name, 1, 'completed');

    $this->migrator->rollback();

    Event::assertDispatched(DataMigrationStarted::class, fn (DataMigrationStarted $event): bool => $event->name === $name
        && $event->method === 'down');
    Event::assertDispatched(DataMigrationEnded::class, fn (DataMigrationEnded $event): bool => $event->name === $name
        && $event->method === 'down'
        && $event->rowsAffected === 2);
});

it('dispatches a failed event with method down when the rollback throws', function (): void {
    $file = $this->createTestMigration('evented_rollback_failure', dataMigrationContent('', '', "throw new RuntimeException('down boom');"));
    $name = $this->migrator->getMigrationName($file);
    insertDataMigrationRecord($name, 1, 'completed');

    expect(fn () => $this->migrator->rollback())->toThrow(RuntimeException::class, 'down boom');

    Event::assertDispatched(DataMigrationFailed::class, fn (DataMigrationFailed $event): bool => $event->name === $name
        && $event->method === 'down'
        && $event->exception->getMessage() === 'down boom');
    Event::assertNotDispatched(DataMigrationEnded::class);
});

it('dispatches a no pending event when there is nothing to migrate', function (): void {
    $this->migrator->run();

    Event::assertDispatched(NoPendingDataMigrations::class, fn (NoPendingDataMigrations $event): bool => $event->method === 'up');
});

it('dispatches a no pending event when there is nothing to rollback', function (): void {
    $this->migrator->rollback();

    Event::assertDispatched(NoPendingDataMigrations::class, fn (NoPendingDataMigrations $event): bool => $event->method === 'down');
});

it('dispatches no events during a dry run', function (): void {
    $this->createTestMigration('evented_dry_run', dataMigrationContent('$this->affected(1);'));

    $this->migrator->run(['dry-run' => true]);

    Event::assertNothingDispatched();
});

it('dispatches nothing for a skipped non reversible migration', function (): void {
    $file = $this->createTestMigration('evented_not_reversible', dataMigrationContent());
    insertDataMigrationRecord($this->migrator->getMigrationName($file), 1, 'completed');

    $this->migrator->rollback();

    Event::assertNothingDispatched();
});

it('dispatches nothing when no dispatcher is provided', function (): void {
    $migrator = new Migrator(
        app(MigrationRepositoryInterface::class),
        app('db'),
        app(MigrationFileResolverInterface::class),
        new NullBackupService,
    );
    $this->createTestMigration('unevented', dataMigrationContent('$this->affected(1);'));

    $migrator->run();

    Event::assertNothingDispatched();
});
