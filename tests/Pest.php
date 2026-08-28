<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function dataMigrationContent(string $up = '', string $properties = '', ?string $down = null): string
{
    $downMethod = $down === null ? '' : <<<PHP
    public function down(): void
    {
        {$down}
    }
PHP;

    return <<<PHP
<?php

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    {$properties}

    public function up(): void
    {
        {$up}
    }

{$downMethod}
};
PHP;
}

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
