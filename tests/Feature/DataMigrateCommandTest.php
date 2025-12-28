<?php

declare(strict_types=1);

beforeEach(function (): void {
    // Ensure data migrations table exists
    $this->artisan('migrate');
});

it('shows nothing to migrate when no pending migrations', function (): void {
    $this->artisan('data:migrate')
        ->assertSuccessful();
});

it('runs pending migrations', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Test migration';
    protected array $affectedTables = ['users'];

    public function up(): void
    {
        $this->affected(10);
    }
};
PHP;

    $this->createTestMigration('run_test_migration', $content);

    $this->artisan('data:migrate')
        ->expectsOutputToContain('Migrating:')
        ->expectsOutputToContain('Migrated:')
        ->assertSuccessful();

    $this->assertDatabaseHas('data_migrations', [
        'status' => 'completed',
    ]);
});

it('supports dry run mode', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Dry run test';
    protected array $affectedTables = ['users'];

    public function up(): void
    {
        $this->affected(5);
    }
};
PHP;

    $this->createTestMigration('dry_run_test', $content);

    $this->artisan('data:migrate', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN MODE')
        ->expectsOutputToContain('[DRY RUN]')
        ->assertSuccessful();

    // Should not have recorded the migration
    $this->assertDatabaseMissing('data_migrations', [
        'migration' => 'dry_run_test',
    ]);
});

it('requires force flag in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('data:migrate')
        ->expectsConfirmation('You are about to run data migrations in production. Do you wish to continue?', 'no')
        ->assertFailed();
});

it('proceeds in production with force flag', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('data:migrate', ['--force' => true])
        ->assertSuccessful();
});
