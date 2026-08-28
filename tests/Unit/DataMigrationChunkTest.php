<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vherbaut\DataMigrations\Migration\DataMigration;

function seedChunkItems(int $count, string $status = 'pending'): void
{
    DB::table('chunk_items')->insert(array_map(
        fn (int $index): array => ['code' => sprintf('item-%02d', $index), 'status' => $status],
        range(1, $count),
    ));
}

/** @param array<int, string> $codes */
function seedChunkCodes(array $codes): void
{
    DB::table('chunk_codes')->insert(array_map(fn (string $code): array => ['code' => $code], $codes));
}

function chunkTestMigration(?string $column = null): DataMigration
{
    return new class($column) extends DataMigration
    {
        public function __construct(?string $column)
        {
            if ($column !== null) {
                $this->chunkColumn = $column;
            }
        }

        public function up(): void {}

        public function runChunk(string $table, callable $callback, ?int $chunkSize = null, ?string $column = null): int
        {
            return $this->chunk($table, $callback, $chunkSize, $column);
        }

        public function runChunkLazy(string $table, callable $callback, ?int $chunkSize = null, ?string $column = null): int
        {
            return $this->chunkLazy($table, $callback, $chunkSize, $column);
        }

        /** @param array<string, mixed> $updates */
        public function runChunkUpdate(string $table, array $updates, callable $whereCallback, ?int $chunkSize = null, ?string $column = null): int
        {
            return $this->chunkUpdate($table, $updates, $whereCallback, $chunkSize, $column);
        }
    };
}

beforeEach(function (): void {
    Schema::create('chunk_items', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
        $table->string('status')->default('pending');
        $table->boolean('flag')->default(false);
    });

    Schema::create('chunk_codes', function (Blueprint $table): void {
        $table->string('code')->primary();
        $table->string('status')->default('pending');
    });

    $this->migration = chunkTestMigration();
});

it('processes every row in id order with chunk', function (): void {
    seedChunkItems(5);
    $seen = [];

    $processed = $this->migration->runChunk('chunk_items', function (object $record) use (&$seen): void {
        $seen[] = $record->id;
    }, 2);

    expect($processed)->toBe(5)
        ->and($seen)->toBe([1, 2, 3, 4, 5]);
});

it('queries the table once per chunk', function (): void {
    seedChunkItems(5);
    DB::enableQueryLog();

    $this->migration->runChunk('chunk_items', fn (object $record) => null, 2);

    expect(DB::getQueryLog())->toHaveCount(3);
});

it('paginates chunk by key instead of offset', function (): void {
    seedChunkItems(5);
    DB::enableQueryLog();

    $this->migration->runChunk('chunk_items', fn (object $record) => null, 2);

    $queries = array_column(DB::getQueryLog(), 'query');

    expect($queries[1])->toContain('"id" > ?')
        ->and(implode(' ', $queries))->not->toContain('offset');
});

it('processes every row in id order with chunkLazy', function (): void {
    seedChunkItems(5);
    $seen = [];

    $processed = $this->migration->runChunkLazy('chunk_items', function (object $record) use (&$seen): void {
        $seen[] = $record->id;
    }, 2);

    expect($processed)->toBe(5)
        ->and($seen)->toBe([1, 2, 3, 4, 5]);
});

it('updates every matching row with chunkUpdate when the update narrows the predicate', function (): void {
    seedChunkItems(5);
    DB::table('chunk_items')->insert([['code' => 'done-1', 'status' => 'done'], ['code' => 'done-2', 'status' => 'done']]);

    $affected = $this->migration->runChunkUpdate(
        'chunk_items',
        ['status' => 'done'],
        fn (Builder $query) => $query->where('status', 'pending'),
        2,
    );

    expect($affected)->toBe(5)
        ->and(DB::table('chunk_items')->where('status', 'done')->count())->toBe(7);
});

it('returns zero on an empty table', function (): void {
    expect($this->migration->runChunk('chunk_items', fn (object $record) => null))->toBe(0)
        ->and($this->migration->runChunkLazy('chunk_items', fn (object $record) => null))->toBe(0)
        ->and($this->migration->runChunkUpdate('chunk_items', ['status' => 'done'], fn (Builder $query) => $query))->toBe(0);
});

it('chunks by a custom column from the chunkColumn property', function (): void {
    seedChunkCodes(['c', 'a', 'b']);
    $seen = [];

    $processed = chunkTestMigration('code')->runChunk('chunk_codes', function (object $record) use (&$seen): void {
        $seen[] = $record->code;
    }, 2);

    expect($processed)->toBe(3)
        ->and($seen)->toBe(['a', 'b', 'c']);
});

it('chunks by a custom column passed as parameter', function (): void {
    seedChunkCodes(['c', 'a', 'b']);
    $seen = [];

    $processed = $this->migration->runChunk('chunk_codes', function (object $record) use (&$seen): void {
        $seen[] = $record->code;
    }, 2, 'code');

    expect($processed)->toBe(3)
        ->and($seen)->toBe(['a', 'b', 'c']);
});

it('lazily chunks by a custom column from the chunkColumn property', function (): void {
    seedChunkCodes(['c', 'a', 'b']);
    $seen = [];

    $processed = chunkTestMigration('code')->runChunkLazy('chunk_codes', function (object $record) use (&$seen): void {
        $seen[] = $record->code;
    }, 2);

    expect($processed)->toBe(3)
        ->and($seen)->toBe(['a', 'b', 'c']);
});

it('lazily chunks by a custom column passed as parameter', function (): void {
    seedChunkCodes(['c', 'a', 'b']);
    $seen = [];

    $processed = $this->migration->runChunkLazy('chunk_codes', function (object $record) use (&$seen): void {
        $seen[] = $record->code;
    }, 2, 'code');

    expect($processed)->toBe(3)
        ->and($seen)->toBe(['a', 'b', 'c']);
});

it('updates by a custom column from the chunkColumn property', function (): void {
    seedChunkCodes(['c', 'a', 'b']);

    $affected = chunkTestMigration('code')->runChunkUpdate(
        'chunk_codes',
        ['status' => 'done'],
        fn (Builder $query) => $query->where('status', 'pending'),
        2,
    );

    expect($affected)->toBe(3)
        ->and(DB::table('chunk_codes')->where('status', 'done')->count())->toBe(3);
});

it('updates by a custom column passed as parameter', function (): void {
    seedChunkCodes(['c', 'a', 'b']);

    $affected = $this->migration->runChunkUpdate(
        'chunk_codes',
        ['status' => 'done'],
        fn (Builder $query) => $query->where('status', 'pending'),
        2,
        'code',
    );

    expect($affected)->toBe(3)
        ->and(DB::table('chunk_codes')->where('status', 'done')->count())->toBe(3);
});

it('terminates with a predicate the update does not narrow', function (): void {
    seedChunkItems(5);

    $affected = $this->migration->runChunkUpdate(
        'chunk_items',
        ['flag' => true],
        fn (Builder $query) => $query->where('status', 'pending'),
        2,
    );

    expect($affected)->toBe(5)
        ->and(DB::table('chunk_items')->where('flag', true)->count())->toBe(5);
});

it('only updates rows matching the predicate inside the key range', function (): void {
    seedChunkItems(5);
    DB::table('chunk_items')->where('id', 3)->update(['status' => 'done']);

    $affected = $this->migration->runChunkUpdate(
        'chunk_items',
        ['flag' => true],
        fn (Builder $query) => $query->where('status', 'pending'),
        3,
    );

    expect($affected)->toBe(4)
        ->and(DB::table('chunk_items')->where('id', 3)->value('flag'))->toBe(0)
        ->and(DB::table('chunk_items')->where('flag', true)->count())->toBe(4);
});
