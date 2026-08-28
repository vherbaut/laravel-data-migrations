<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->artisan('migrate');
});

it('shows nothing to rollback when no migrations ran', function (): void {
    $this->artisan('data:rollback')
        ->assertSuccessful();
});

it('rolls back completed migrations', function (): void {
    // Create and run a migration first
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Rollback test';

    public function up(): void
    {
        $this->affected(5);
    }

    public function down(): void
    {
        // Rollback logic
    }
};
PHP;

    $this->createTestMigration('rollback_test', $content);
    $this->artisan('data:migrate', ['--force' => true]);

    $this->assertDatabaseHas('data_migrations', [
        'status' => 'completed',
    ]);

    $this->artisan('data:rollback', ['--force' => true])
        ->expectsOutputToContain('Rolling back:')
        ->expectsOutputToContain('Rolled back:')
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', [
        'status' => 'rolled_back',
    ]);
});

it('skips non-reversible migrations', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Non-reversible test';

    public function up(): void
    {
        $this->affected(5);
    }

    // No down() method - not reversible
};
PHP;

    $this->createTestMigration('non_reversible_test', $content);
    $this->artisan('data:migrate', ['--force' => true]);

    $this->artisan('data:rollback', ['--force' => true])
        ->expectsOutputToContain('Skipping (not reversible)')
        ->assertSuccessful();
});

it('rolls back specific batch number', function (): void {
    // Create batch 1
    $content1 = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}
    public function down(): void {}
};
PHP;

    $this->createTestMigration('batch1_migration', $content1);
    $this->artisan('data:migrate', ['--force' => true]);

    sleep(1); // Ensure different timestamp

    // Create batch 2
    $content2 = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}
    public function down(): void {}
};
PHP;

    $this->createTestMigration('batch2_migration', $content2);
    $this->artisan('data:migrate', ['--force' => true]);

    // Rollback only batch 1
    $this->artisan('data:rollback', ['--batch' => 1, '--force' => true])
        ->assertSuccessful();
});

it('requires force flag in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('data:rollback')
        ->expectsConfirmation('You are about to rollback data migrations in production. This may cause data loss. Continue?', 'no')
        ->assertFailed();
});

it('rollback skips already rolled back migrations and rolls back previous batch', function (): void {
    // Create and run migration A (batch 1)
    $contentA = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Migration A';

    public function up(): void
    {
        $this->affected(1);
    }

    public function down(): void {}
};
PHP;

    $filepathA = $this->createTestMigration('migration_a', $contentA);
    $migrationNameA = pathinfo($filepathA, PATHINFO_FILENAME);

    $this->artisan('data:migrate', ['--force' => true]);

    $this->assertDatabaseHas('data_migrations', [
        'migration' => $migrationNameA,
        'status' => 'completed',
        'batch' => 1,
    ]);

    sleep(1);

    // Create and run migration B (batch 2)
    $contentB = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Migration B';

    public function up(): void
    {
        $this->affected(2);
    }

    public function down(): void {}
};
PHP;

    $filepathB = $this->createTestMigration('migration_b', $contentB);
    $migrationNameB = pathinfo($filepathB, PATHINFO_FILENAME);

    $this->artisan('data:migrate', ['--force' => true]);

    $this->assertDatabaseHas('data_migrations', [
        'migration' => $migrationNameB,
        'status' => 'completed',
        'batch' => 2,
    ]);

    // Rollback batch 2 (migration B)
    $this->artisan('data:rollback', ['--force' => true])
        ->expectsOutputToContain('Rolling back:')
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', [
        'migration' => $migrationNameB,
        'status' => 'rolled_back',
    ]);

    // Rollback again - should rollback batch 1 (migration A), not try B again
    $this->artisan('data:rollback', ['--force' => true])
        ->expectsOutputToContain('Rolling back:')
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', [
        'migration' => $migrationNameA,
        'status' => 'rolled_back',
    ]);
});

it('can re-run migration after rollback', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Re-run after rollback test';

    public function up(): void
    {
        $this->affected(10);
    }

    public function down(): void
    {
        // Rollback logic
    }
};
PHP;

    $filepath = $this->createTestMigration('rerun_after_rollback', $content);
    $migrationName = pathinfo($filepath, PATHINFO_FILENAME);

    // Step 1: Run the migration
    $this->artisan('data:migrate', ['--force' => true])
        ->expectsOutputToContain('Migrating:')
        ->expectsOutputToContain('Migrated:')
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', [
        'migration' => $migrationName,
        'status' => 'completed',
    ]);

    // Step 2: Rollback the migration
    $this->artisan('data:rollback', ['--force' => true])
        ->expectsOutputToContain('Rolling back:')
        ->expectsOutputToContain('Rolled back:')
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', [
        'migration' => $migrationName,
        'status' => 'rolled_back',
    ]);

    // Step 3: Re-run the migration
    $this->artisan('data:migrate', ['--force' => true])
        ->expectsOutputToContain('Migrating:')
        ->expectsOutputToContain('Migrated:')
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', [
        'migration' => $migrationName,
        'status' => 'completed',
    ]);

    // Should only have one record for this migration
    $count = DB::table('data_migrations')
        ->where('migration', $migrationName)
        ->count();

    expect($count)->toBe(1);
});

it('rollback --step ignores failed and rolled back records', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}
    public function down(): void {}
};
PHP;

    $filepath = $this->createTestMigration('step_target', $content);
    $migrationName = pathinfo($filepath, PATHINFO_FILENAME);
    $this->artisan('data:migrate', ['--force' => true]);

    insertDataMigrationRecord('zzz_failed', 2, 'failed');
    insertDataMigrationRecord('zzz_rolled_back', 3, 'rolled_back');

    $this->artisan('data:rollback', ['--step' => 1, '--force' => true])
        ->expectsOutputToContain("Rolled back: {$migrationName}")
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', ['migration' => $migrationName, 'status' => 'rolled_back']);
    $this->assertDatabaseHas('data_migrations', ['migration' => 'zzz_failed', 'status' => 'failed']);
    $this->assertDatabaseHas('data_migrations', ['migration' => 'zzz_rolled_back', 'status' => 'rolled_back']);
});

it('rollback --batch does not run down() again for already rolled back migrations', function (): void {
    Schema::create('rollback_log', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $contentA = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}

    public function down(): void
    {
        DB::table('rollback_log')->insert(['name' => 'a']);
    }
};
PHP;

    $contentB = <<<'PHP'
<?php

use Illuminate\Support\Facades\DB;
use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}

    public function down(): void
    {
        DB::table('rollback_log')->insert(['name' => 'b']);
    }
};
PHP;

    $this->createTestMigration('a_first', $contentA);
    $this->createTestMigration('b_second', $contentB);
    $this->artisan('data:migrate', ['--force' => true]);

    $this->artisan('data:rollback', ['--step' => 1, '--force' => true]);
    $this->artisan('data:rollback', ['--batch' => 1, '--force' => true])
        ->assertSuccessful();

    expect(DB::table('rollback_log')->orderBy('name')->pluck('name')->all())->toBe(['a', 'b']);
});

it('rollback --batch reports nothing when the batch only contains rolled back records', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}
    public function down(): void {}
};
PHP;

    $this->createTestMigration('fully_rolled_back', $content);
    $this->artisan('data:migrate', ['--force' => true]);
    $this->artisan('data:rollback', ['--force' => true]);

    $this->artisan('data:rollback', ['--batch' => 1, '--force' => true])
        ->expectsOutputToContain('No migrations found for batch 1')
        ->assertSuccessful();
});
