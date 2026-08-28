<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function insertDataMigrationRecord(string $migration, int $batch, string $status): void
{
    DB::table('data_migrations')->insert([
        'migration' => $migration,
        'batch' => $batch,
        'status' => $status,
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
