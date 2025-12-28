<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->artisan('migrate');
});

it('shows no migrations found when empty', function (): void {
    $this->artisan('data:status')
        ->expectsOutputToContain('No migrations found')
        ->assertSuccessful();
});

it('shows pending migrations', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Status test';

    public function up(): void
    {
        $this->affected(5);
    }
};
PHP;

    $this->createTestMigration('status_test', $content);

    $this->artisan('data:status')
        ->expectsOutputToContain('status_test')
        ->expectsOutputToContain('Pending')
        ->assertSuccessful();
});

it('shows completed migrations', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    protected string $description = 'Completed test';

    public function up(): void
    {
        $this->affected(10);
    }
};
PHP;

    $this->createTestMigration('completed_test', $content);
    $this->artisan('data:migrate', ['--force' => true]);

    $this->artisan('data:status')
        ->expectsOutputToContain('completed_test')
        ->expectsOutputToContain('Completed')
        ->assertSuccessful();
});

it('filters pending migrations only', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}
};
PHP;

    $this->createTestMigration('pending_filter_test', $content);
    $this->artisan('data:migrate', ['--force' => true]);

    sleep(1);

    $this->createTestMigration('still_pending_test', $content);

    $this->artisan('data:status', ['--pending' => true])
        ->expectsOutputToContain('Pending')
        ->assertSuccessful();
});

it('filters ran migrations only', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}
};
PHP;

    $this->createTestMigration('ran_filter_test', $content);
    $this->artisan('data:migrate', ['--force' => true]);

    sleep(1);

    $this->createTestMigration('not_ran_test', $content);

    $this->artisan('data:status', ['--ran' => true])
        ->expectsOutputToContain('Completed')
        ->assertSuccessful();
});

it('shows summary counts', function (): void {
    $content = <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration {
    public function up(): void {}
};
PHP;

    $this->createTestMigration('summary_test', $content);

    $this->artisan('data:status')
        ->expectsOutputToContain('Total:')
        ->assertSuccessful();
});
