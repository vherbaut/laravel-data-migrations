<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->artisan('migrate');
});

it('resets and re-runs all migrations', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Fresh test';

    public function up(): void
    {
        $this->affected(5);
    }
};
PHP;

    $this->createTestMigration('fresh_test', $content);
    $this->artisan('data:migrate', ['--force' => true]);

    $this->assertDatabaseHas('data_migrations', [
        'status' => 'completed',
    ]);

    $this->artisan('data:fresh', ['--force' => true])
        ->expectsOutputToContain('Resetting data migration records')
        ->expectsOutputToContain('Reset:')
        ->assertSuccessful();

    // Should have completed again after fresh
    $this->assertDatabaseHas('data_migrations', [
        'status' => 'completed',
    ]);
});

it('requires force flag in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('data:fresh')
        ->expectsConfirmation('You are about to reset ALL data migration records in production. This is DANGEROUS. Continue?', 'no')
        ->assertFailed();
});

it('fails when migrations table does not exist', function (): void {
    // Drop the migrations table
    \Illuminate\Support\Facades\Schema::dropIfExists('data_migrations');

    $this->artisan('data:fresh', ['--force' => true])
        ->expectsOutputToContain('Data migrations table not found')
        ->assertFailed();
});
