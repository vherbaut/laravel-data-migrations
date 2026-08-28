<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/*
 * Upgrades a 1.x tracking table to the 2.0 shape: the status enum becomes a
 * string without default, the counters become unsigned big integers and an
 * index is added on status. Safe to re-run, and a no-op on a table created
 * by the 2.0 create migration.
 */
return new class extends Migration
{
    /**
     * The connection holding the tracking table (null = default connection).
     *
     * @return string|null
     */
    public function getConnection(): ?string
    {
        /** @var string|null $connection */
        $connection = config('data-migrations.connection');

        return $connection;
    }

    /**
     * @return void
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $table = $this->tableName();

        if (! $schema->hasTable($table)) {
            return;
        }

        if ($this->columnsNeedUpgrade($schema, $table)) {
            $this->dropStatusCheckConstraint($schema, $table);
            $this->upgradeColumns($schema, $table);
        }

        if (! $schema->hasIndex($table, ['batch', 'status'])) {
            $schema->table($table, fn (Blueprint $blueprint) => $blueprint->index(['batch', 'status']));
        }

        if (! $schema->hasIndex($table, ['status'])) {
            $schema->table($table, fn (Blueprint $blueprint) => $blueprint->index('status'));
        }
    }

    /**
     * The 1.x shape is never restored: downgrading the package requires
     * restoring a backup of the tracking table.
     *
     * @return void
     */
    public function down(): void {}

    /**
     * @return string
     */
    private function tableName(): string
    {
        /** @var string $table */
        $table = config('data-migrations.table', 'data_migrations');

        return $table;
    }

    /**
     * Detect the 1.x shape from what every driver reports: status still has a
     * default (2.0 has none) or is a native enum (MySQL), or a counter is still
     * a 32-bit integer (int on MySQL and SQL Server, int4 on PostgreSQL). SQLite
     * reports "integer" for both widths, so only the status signal counts there.
     *
     * @param Builder $schema
     * @param string $table
     * @return bool
     */
    private function columnsNeedUpgrade(Builder $schema, string $table): bool
    {
        foreach ($schema->getColumns($table) as $column) {
            $name = strtolower((string) $column['name']);
            $type = strtolower((string) $column['type_name']);

            if ($name === 'status') {
                if ($column['default'] !== null) {
                    return true;
                }

                if ($type === 'enum') {
                    return true;
                }
            }

            if (in_array($name, ['rows_affected', 'duration_ms'], true)) {
                if (in_array($type, ['int', 'int4'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * PostgreSQL and SQL Server keep the CHECK constraint that enum() created
     * when the column type changes, and Laravel never named it: look it up in
     * the catalog and drop it. MySQL uses a native enum and SQLite drops the
     * constraint in the table rebuild performed by change().
     *
     * @param Builder $schema
     * @param string $table
     * @return void
     */
    private function dropStatusCheckConstraint(Builder $schema, string $table): void
    {
        $connection = $schema->getConnection();
        $grammar = $connection->getSchemaGrammar();
        $wrappedTable = $grammar->wrapTable($table);

        $constraints = match ($connection->getDriverName()) {
            'pgsql' => $connection->select(
                'select con.conname as name from pg_constraint con '
                ."where con.conrelid = ?::regclass and con.contype = 'c' "
                .'and exists (select 1 from pg_attribute att where att.attrelid = con.conrelid '
                .'and att.attnum = any (con.conkey) and att.attname = ?)',
                [$wrappedTable, 'status'],
            ),
            'sqlsrv' => $connection->select(
                'select cc.name as name from sys.check_constraints cc '
                .'left join sys.columns c on c.object_id = cc.parent_object_id and c.column_id = cc.parent_column_id '
                .'where cc.parent_object_id = object_id(?) '
                .'and (c.name = ? or (cc.parent_column_id = 0 and cc.definition like ?))',
                [$wrappedTable, 'status', '%[status]%'],
            ),
            default => [],
        };

        foreach ($constraints as $constraint) {
            $connection->statement(sprintf('alter table %s drop constraint %s', $wrappedTable, $grammar->wrap((string) $constraint->name)));
        }
    }

    /**
     * @param Builder $schema
     * @param string $table
     * @return void
     */
    private function upgradeColumns(Builder $schema, string $table): void
    {
        if ($schema->getConnection()->getDriverName() === 'sqlsrv') {
            // SQL Server refuses to shrink an nvarchar column that an index depends on (error 5074).
            $this->dropIndexesOn($schema, $table, ['batch', 'status']);
        }

        $schema->table($table, function (Blueprint $blueprint): void {
            $blueprint->string('status', 20)->change();
            $blueprint->unsignedBigInteger('rows_affected')->nullable()->change();
            $blueprint->unsignedBigInteger('duration_ms')->nullable()->change();
        });
    }

    /**
     * @param Builder $schema
     * @param string $table
     * @param array<int, string> $columns
     * @return void
     */
    private function dropIndexesOn(Builder $schema, string $table, array $columns): void
    {
        $names = [];

        foreach ($schema->getIndexes($table) as $index) {
            if ($index['primary']) {
                continue;
            }

            if ($index['columns'] === $columns) {
                $names[] = (string) $index['name'];
            }
        }

        if ($names === []) {
            return;
        }

        $schema->table($table, function (Blueprint $blueprint) use ($names): void {
            foreach ($names as $name) {
                $blueprint->dropIndex($name);
            }
        });
    }
};
