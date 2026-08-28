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

beforeEach(function (): void {
    Schema::create('chunk_items', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
        $table->string('status')->default('pending');
        $table->boolean('flag')->default(false);
    });

    $this->migration = new class extends DataMigration
    {
        public function up(): void {}

        public function runChunk(string $table, callable $callback, ?int $chunkSize = null): int
        {
            return $this->chunk($table, $callback, $chunkSize);
        }

        public function runChunkLazy(string $table, callable $callback, ?int $chunkSize = null): int
        {
            return $this->chunkLazy($table, $callback, $chunkSize);
        }

        /** @param array<string, mixed> $updates */
        public function runChunkUpdate(string $table, array $updates, callable $whereCallback, ?int $chunkSize = null): int
        {
            return $this->chunkUpdate($table, $updates, $whereCallback, $chunkSize);
        }
    };
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
