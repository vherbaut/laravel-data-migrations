<?php

declare(strict_types=1);

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
