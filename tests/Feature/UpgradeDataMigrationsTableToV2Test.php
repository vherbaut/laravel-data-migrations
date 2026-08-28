<?php

declare(strict_types=1);

/*
 * These tests run on SQLite. The pgsql and sqlsrv branches of the upgrade
 * migration (CHECK constraint lookup, index drop before shrinking nvarchar)
 * cannot be exercised here and were reviewed against the vendor grammars.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const LEGACY_TRACKING_STATUSES = ['pending', 'running', 'completed', 'failed', 'rolled_back'];

function legacyTrackingTableMigration(): Migration
{
    return require __DIR__.'/../fixtures/legacy/2024_01_01_000000_create_data_migrations_table_v1.php';
}

function currentTrackingTableMigration(): Migration
{
    return require __DIR__.'/../../database/migrations/2024_01_01_000000_create_data_migrations_table.php';
}

function upgradeTrackingTableMigration(): Migration
{
    $files = glob(__DIR__.'/../../database/upgrades/*_upgrade_data_migrations_table_to_v2.php');

    return require $files[0];
}

/** @return array<string, array{type: string, nullable: bool, default: mixed, auto_increment: bool}> */
function trackingTableShape(string $table = 'data_migrations'): array
{
    $shape = [];

    foreach (Schema::getColumns($table) as $column) {
        $shape[$column['name']] = [
            'type' => $column['type'],
            'nullable' => $column['nullable'],
            'default' => $column['default'],
            'auto_increment' => $column['auto_increment'],
        ];
    }

    return $shape;
}

/** @return array<int, array<int, string>> */
function trackingTableIndexColumns(string $table = 'data_migrations'): array
{
    $columns = array_map(fn (array $index): array => $index['columns'], Schema::getIndexes($table));
    sort($columns);

    return $columns;
}

/** @return array<int, string> */
function ddlStatementsLogged(): array
{
    return array_values(array_filter(
        array_column(DB::getQueryLog(), 'query'),
        fn (string $sql): bool => preg_match('/^\s*(alter|create|drop)\b/i', $sql) === 1,
    ));
}

beforeEach(function (): void {
    Schema::dropIfExists('data_migrations');
    legacyTrackingTableMigration()->up();

    foreach (LEGACY_TRACKING_STATUSES as $batch => $status) {
        insertDataMigrationRecord("m_{$status}", $batch + 1, $status);
    }

    DB::table('data_migrations')->where('migration', 'm_completed')->update(['rows_affected' => 4294967295, 'duration_ms' => 12]);
});

it('starts from a 1.x shaped table', function (): void {
    expect(trackingTableShape()['status']['default'])->not->toBeNull()
        ->and(Schema::hasIndex('data_migrations', ['status']))->toBeFalse()
        ->and(fn () => insertDataMigrationRecord('m_unknown', 9, 'skipped'))->toThrow(QueryException::class);
});

it('removes the status check constraint and default', function (): void {
    upgradeTrackingTableMigration()->up();

    insertDataMigrationRecord('m_unknown', 9, 'skipped');
    $definition = DB::selectOne("select sql from sqlite_master where type = 'table' and name = 'data_migrations'")->sql;

    expect(trackingTableShape()['status']['default'])->toBeNull()
        ->and(strtolower($definition))->not->toContain('check');
});

it('reports the 2.0 column types', function (): void {
    upgradeTrackingTableMigration()->up();

    $shape = trackingTableShape();
    DB::table('data_migrations')->insert(['migration' => 'm_new', 'batch' => 9, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);

    expect(Schema::getColumnType('data_migrations', 'status'))->toBe('varchar')
        ->and($shape['status']['nullable'])->toBeFalse()
        ->and($shape['rows_affected']['nullable'])->toBeTrue()
        ->and($shape['duration_ms']['nullable'])->toBeTrue()
        ->and($shape['id']['auto_increment'])->toBeTrue()
        ->and(DB::table('data_migrations')->where('migration', 'm_new')->value('id'))->toBeGreaterThan(5);
});

it('keeps every row and value', function (): void {
    upgradeTrackingTableMigration()->up();

    expect(DB::table('data_migrations')->count())->toBe(5)
        ->and(DB::table('data_migrations')->orderBy('batch')->pluck('status')->all())->toBe(LEGACY_TRACKING_STATUSES)
        ->and(DB::table('data_migrations')->where('migration', 'm_completed')->value('rows_affected'))->toBe(4294967295)
        ->and(DB::table('data_migrations')->where('migration', 'm_completed')->value('duration_ms'))->toBe(12);
});

it('adds the status index and keeps the existing indexes', function (): void {
    upgradeTrackingTableMigration()->up();

    expect(Schema::hasIndex('data_migrations', ['status']))->toBeTrue()
        ->and(Schema::hasIndex('data_migrations', ['batch', 'status']))->toBeTrue()
        ->and(Schema::hasIndex('data_migrations', ['migration'], 'unique'))->toBeTrue();
});

it('is a no-op when run twice', function (): void {
    upgradeTrackingTableMigration()->up();
    $shape = trackingTableShape();
    $indexes = trackingTableIndexColumns();

    DB::enableQueryLog();
    upgradeTrackingTableMigration()->up();

    expect(ddlStatementsLogged())->toBe([])
        ->and(trackingTableShape())->toBe($shape)
        ->and(trackingTableIndexColumns())->toBe($indexes)
        ->and(DB::table('data_migrations')->count())->toBe(5);
});

it('produces the same shape as the 2.0 create migration and is a no-op on it', function (): void {
    upgradeTrackingTableMigration()->up();
    $upgradedShape = trackingTableShape();
    $upgradedIndexes = trackingTableIndexColumns();

    Schema::drop('data_migrations');
    currentTrackingTableMigration()->up();

    DB::enableQueryLog();
    upgradeTrackingTableMigration()->up();

    expect(trackingTableShape())->toBe($upgradedShape)
        ->and(trackingTableIndexColumns())->toBe($upgradedIndexes)
        ->and(ddlStatementsLogged())->toBe([]);
});

it('does nothing when the tracking table does not exist', function (): void {
    Schema::drop('data_migrations');

    upgradeTrackingTableMigration()->up();

    expect(Schema::hasTable('data_migrations'))->toBeFalse();
});

it('honours the configured table name', function (): void {
    config(['data-migrations.table' => 'tracking_v1']);
    legacyTrackingTableMigration()->up();

    upgradeTrackingTableMigration()->up();

    expect(Schema::hasIndex('tracking_v1', ['status']))->toBeTrue()
        ->and(Schema::hasIndex('data_migrations', ['status']))->toBeFalse();
});

it('targets the configured connection', function (): void {
    expect(upgradeTrackingTableMigration()->getConnection())->toBeNull();

    config(['data-migrations.connection' => 'testing']);

    expect(upgradeTrackingTableMigration()->getConnection())->toBe('testing');
});
