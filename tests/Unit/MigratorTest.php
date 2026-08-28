<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vherbaut\DataMigrations\Contracts\BackupServiceInterface;
use Vherbaut\DataMigrations\Contracts\MigrationFileResolverInterface;
use Vherbaut\DataMigrations\Contracts\MigrationRepositoryInterface;
use Vherbaut\DataMigrations\Exceptions\UnresolvedMigrationsException;
use Vherbaut\DataMigrations\Migration\Migrator;

beforeEach(function (): void {
    $this->artisan('migrate');

    Schema::create('widgets', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $this->backup = new class implements BackupServiceInterface
    {
        public bool $available = true;

        public bool $result = true;

        /** @var array<int, array{0: array<int, string>, 1: string}> */
        public array $calls = [];

        public function backupTables(array $tables, string $migrationName): bool
        {
            $this->calls[] = [$tables, $migrationName];

            return $this->result;
        }

        public function isAvailable(): bool
        {
            return $this->available;
        }
    };

    $this->migrator = new Migrator(
        app(MigrationRepositoryInterface::class),
        app('db'),
        app(MigrationFileResolverInterface::class),
        $this->backup,
    );
});

it('rolls back the migration changes when a migration throws inside a transaction in auto mode', function (): void {
    $this->createTestMigration('failing_in_transaction', dataMigrationContent(
        "DB::table('widgets')->insert(['name' => 'one']); throw new RuntimeException('boom');",
    ));

    expect(fn () => $this->migrator->run())->toThrow(RuntimeException::class, 'boom')
        ->and(DB::table('widgets')->count())->toBe(0)
        ->and(DB::table('data_migrations')->value('status'))->toBe('failed')
        ->and(DB::table('data_migrations')->value('error_message'))->toBe('boom');
});

it('keeps the migration changes when withinTransaction is false in auto mode', function (): void {
    $this->createTestMigration('failing_without_transaction', dataMigrationContent(
        "DB::table('widgets')->insert(['name' => 'one']); throw new RuntimeException('boom');",
        'protected bool $withinTransaction = false;',
    ));

    expect(fn () => $this->migrator->run())->toThrow(RuntimeException::class)
        ->and(DB::table('widgets')->count())->toBe(1)
        ->and(DB::table('data_migrations')->value('status'))->toBe('failed');
});

it('always wraps the migration in a transaction in always mode', function (): void {
    config()->set('data-migrations.transaction', 'always');

    $this->createTestMigration('failing_forced_transaction', dataMigrationContent(
        "DB::table('widgets')->insert(['name' => 'one']); throw new RuntimeException('boom');",
        'protected bool $withinTransaction = false;',
    ));

    expect(fn () => $this->migrator->run())->toThrow(RuntimeException::class)
        ->and(DB::table('widgets')->count())->toBe(0);
});

it('never wraps the migration in a transaction in never mode', function (): void {
    config()->set('data-migrations.transaction', 'never');

    $this->createTestMigration('failing_forbidden_transaction', dataMigrationContent(
        "DB::table('widgets')->insert(['name' => 'one']); throw new RuntimeException('boom');",
        'protected bool $withinTransaction = true;',
    ));

    expect(fn () => $this->migrator->run())->toThrow(RuntimeException::class)
        ->and(DB::table('widgets')->count())->toBe(1);
});

it('backs up the affected tables before running when auto backup is enabled', function (): void {
    config()->set('data-migrations.safety.auto_backup', true);

    $file = $this->createTestMigration('with_backup', dataMigrationContent(
        "DB::table('widgets')->insert(['name' => 'one']);",
        "protected array \$affectedTables = ['widgets'];",
    ));

    $this->migrator->run();

    expect($this->backup->calls)->toBe([[['widgets'], $this->migrator->getMigrationName($file)]])
        ->and($this->migrator->getNotes())->toContain('<info>Backup created successfully.</info>')
        ->and(DB::table('widgets')->count())->toBe(1);
});

it('does not call the backup service when auto backup is disabled', function (): void {
    config()->set('data-migrations.safety.auto_backup', false);

    $this->createTestMigration('without_backup', dataMigrationContent('', "protected array \$affectedTables = ['widgets'];"));

    $this->migrator->run();

    expect($this->backup->calls)->toBe([]);
});

it('skips the backup when the migration declares no affected tables', function (): void {
    config()->set('data-migrations.safety.auto_backup', true);

    $this->createTestMigration('backup_without_tables', dataMigrationContent(''));

    $this->migrator->run();

    expect($this->backup->calls)->toBe([]);
});

it('notes that the backup service is unavailable and still runs the migration', function (): void {
    config()->set('data-migrations.safety.auto_backup', true);
    $this->backup->available = false;

    $this->createTestMigration('backup_unavailable', dataMigrationContent(
        "DB::table('widgets')->insert(['name' => 'one']);",
        "protected array \$affectedTables = ['widgets'];",
    ));

    $this->migrator->run();

    expect($this->backup->calls)->toBe([])
        ->and($this->migrator->getNotes())->toContain('<fg=yellow>Auto backup enabled but backup service not available.</>')
        ->and(DB::table('widgets')->count())->toBe(1);
});

it('continues when the backup fails', function (): void {
    config()->set('data-migrations.safety.auto_backup', true);
    $this->backup->result = false;

    $this->createTestMigration('backup_failing', dataMigrationContent(
        "DB::table('widgets')->insert(['name' => 'one']);",
        "protected array \$affectedTables = ['widgets'];",
    ));

    $this->migrator->run();

    expect($this->migrator->getNotes())->toContain('<fg=yellow>Backup failed, continuing with migration...</>')
        ->and(DB::table('widgets')->count())->toBe(1)
        ->and(DB::table('data_migrations')->value('status'))->toBe('completed');
});

it('does not log a start record during a dry run', function (): void {
    $file = $this->createTestMigration('dry_run', dataMigrationContent("DB::table('widgets')->insert(['name' => 'one']);"));

    $ran = $this->migrator->run(['dry-run' => true]);

    expect($ran)->toBe([$file])
        ->and(DB::table('data_migrations')->count())->toBe(0)
        ->and(DB::table('widgets')->count())->toBe(0)
        ->and($this->migrator->getNotes())->toContain("<comment>[DRY RUN]</comment> {$this->migrator->getMigrationName($file)}");
});

it('returns the ran files and records completed migrations', function (): void {
    $file = $this->createTestMigration('completed', dataMigrationContent(
        '$this->affected(3);',
        "protected string \$description = 'Counts widgets';",
    ));

    $ran = $this->migrator->run();

    $record = DB::table('data_migrations')->first();

    expect($ran)->toBe([$file])
        ->and($record->status)->toBe('completed')
        ->and($record->rows_affected)->toBe(3)
        ->and($record->duration_ms)->not->toBeNull()
        ->and(json_decode((string) $record->metadata, true))->toBe(['description' => 'Counts widgets']);
});

it('notes a missing file on rollback and leaves the record untouched', function (): void {
    insertDataMigrationRecord('missing_migration', 1, 'completed');

    $rolledBack = $this->migrator->rollback();

    expect($rolledBack)->toBe([])
        ->and($this->migrator->getNotes())->toContain('<fg=yellow>Migration file not found:</> missing_migration')
        ->and(DB::table('data_migrations')->value('status'))->toBe('completed');
});

it('returns records whose migration file is missing as orphaned', function (): void {
    $file = $this->createTestMigration('present', dataMigrationContent());
    insertDataMigrationRecord(basename($file, '.php'), 1, 'completed');
    insertDataMigrationRecord('vanished_completed', 1, 'completed');
    insertDataMigrationRecord('vanished_failed', 2, 'failed');

    expect($this->migrator->getOrphanedMigrations()->pluck('migration')->all())
        ->toBe(['vanished_failed', 'vanished_completed']);
});

it('returns no orphaned migrations when every record has a file', function (): void {
    $file = $this->createTestMigration('present', dataMigrationContent());
    insertDataMigrationRecord(basename($file, '.php'), 1, 'completed');

    expect($this->migrator->getOrphanedMigrations())->toBeEmpty();
});

it('returns no orphaned migrations when the table is empty', function (): void {
    expect($this->migrator->getOrphanedMigrations())->toBeEmpty();
});

it('refuses to run while a failed migration is unresolved', function (): void {
    $failed = $this->createTestMigration('failed_before', dataMigrationContent('$this->affected(1);'));
    $pending = $this->createTestMigration('pending_after', dataMigrationContent('$this->affected(1);'));
    insertDataMigrationRecord($this->migrator->getMigrationName($failed), 1, 'failed');

    expect(fn () => $this->migrator->run())->toThrow(UnresolvedMigrationsException::class, $this->migrator->getMigrationName($failed))
        ->and(DB::table('data_migrations')->where('migration', $this->migrator->getMigrationName($pending))->exists())->toBeFalse()
        ->and(DB::table('data_migrations')->where('migration', $this->migrator->getMigrationName($failed))->value('status'))->toBe('failed');
});

it('refuses to run while a running record is unresolved', function (): void {
    $running = $this->createTestMigration('running_before', dataMigrationContent('$this->affected(1);'));
    insertDataMigrationRecord($this->migrator->getMigrationName($running), 1, 'running');

    expect(fn () => $this->migrator->run())->toThrow(UnresolvedMigrationsException::class);
});

it('exposes the unresolved migrations on the exception', function (): void {
    $failed = $this->createTestMigration('listed_failed', dataMigrationContent());
    insertDataMigrationRecord($this->migrator->getMigrationName($failed), 1, 'failed');

    try {
        $this->migrator->run();
    } catch (UnresolvedMigrationsException $exception) {
        expect($exception->getMigrations())->toBe([$this->migrator->getMigrationName($failed)])
            ->and($exception->getMessage())->toContain('--retry-failed');

        return;
    }

    $this->fail('UnresolvedMigrationsException was not thrown.');
});

it('retries unresolved migrations with the retry-failed option', function (): void {
    $failed = $this->createTestMigration('retried_failed', dataMigrationContent('$this->affected(1);'));
    $running = $this->createTestMigration('retried_running', dataMigrationContent('$this->affected(2);'));
    $pending = $this->createTestMigration('retried_pending', dataMigrationContent('$this->affected(3);'));
    insertDataMigrationRecord($this->migrator->getMigrationName($failed), 1, 'failed');
    insertDataMigrationRecord($this->migrator->getMigrationName($running), 2, 'running');

    $ran = $this->migrator->run(['retry-failed' => true]);

    expect($ran)->toBe([$failed, $pending, $running])
        ->and(DB::table('data_migrations')->pluck('status')->unique()->all())->toBe(['completed'])
        ->and(DB::table('data_migrations')->pluck('batch')->unique()->all())->toBe([3])
        ->and(DB::table('data_migrations')->count())->toBe(3);
});

it('re-runs a failed idempotent migration without the option', function (): void {
    $failed = $this->createTestMigration('idempotent_failed', dataMigrationContent('$this->affected(1);', 'protected bool $idempotent = true;'));
    insertDataMigrationRecord($this->migrator->getMigrationName($failed), 1, 'failed');

    $ran = $this->migrator->run();

    expect($ran)->toBe([$failed])
        ->and(DB::table('data_migrations')->value('status'))->toBe('completed');
});

it('re-runs a rolled back migration without the option', function (): void {
    $rolledBack = $this->createTestMigration('rolled_back_again', dataMigrationContent('$this->affected(1);'));
    insertDataMigrationRecord($this->migrator->getMigrationName($rolledBack), 1, 'rolled_back');

    expect($this->migrator->run())->toBe([$rolledBack])
        ->and(DB::table('data_migrations')->value('status'))->toBe('completed');
});

it('ignores an unresolved record whose file no longer exists', function (): void {
    insertDataMigrationRecord('2024_01_01_000000_ghost', 1, 'failed');
    $pending = $this->createTestMigration('after_ghost', dataMigrationContent('$this->affected(1);'));

    expect($this->migrator->run())->toBe([$pending]);
});

it('lists idempotent but not other unresolved migrations as pending', function (): void {
    $blocked = $this->createTestMigration('blocked_failed', dataMigrationContent());
    $idempotent = $this->createTestMigration('idempotent_running', dataMigrationContent('', 'protected bool $idempotent = true;'));
    $pending = $this->createTestMigration('plain_pending', dataMigrationContent());
    insertDataMigrationRecord($this->migrator->getMigrationName($blocked), 1, 'failed');
    insertDataMigrationRecord($this->migrator->getMigrationName($idempotent), 1, 'running');

    expect($this->migrator->getPendingMigrations())->toBe([$idempotent, $pending]);
});
