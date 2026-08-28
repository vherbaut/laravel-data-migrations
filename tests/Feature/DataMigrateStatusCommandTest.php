<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

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

it('shows orphaned records that have no migration file', function (): void {
    insertDataMigrationRecord('2024_01_01_000000_vanished', 3, 'completed');

    $this->withoutMockingConsoleOutput();
    $exitCode = Artisan::call('data:status');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('| 2024_01_01_000000_vanished |')
        ->toContain('Completed (orphaned)');
});

it('counts orphaned records in the summary', function (): void {
    insertDataMigrationRecord('vanished', 1, 'completed');
    $this->createTestMigration('present', dataMigrationContent());

    $this->artisan('data:status')
        ->expectsOutputToContain('Total: 2 | Pending: 1 | Completed: 1 | Failed: 0 | Orphaned: 1')
        ->assertSuccessful();
});

it('outputs the status list as json', function (): void {
    $file = $this->createTestMigration('present', dataMigrationContent('$this->affected(4);'));
    $this->artisan('data:migrate', ['--force' => true]);
    insertDataMigrationRecord('vanished', 2, 'failed');

    $this->withoutMockingConsoleOutput();
    Artisan::call('data:status', ['--json' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($json)->toHaveCount(2)
        ->and($json[0])->toMatchArray([
            'name' => basename($file, '.php'),
            'status' => 'completed',
            'batch' => 1,
            'rows_affected' => 4,
            'orphaned' => false,
        ])
        ->and($json[0]['duration_ms'])->toBeInt()
        ->and($json[0]['ran_at'])->toBeString()
        ->and($json[1])->toBe([
            'name' => 'vanished',
            'status' => 'failed',
            'batch' => 2,
            'rows_affected' => null,
            'duration_ms' => null,
            'ran_at' => null,
            'orphaned' => true,
        ]);
});

it('outputs an empty json array when there is nothing', function (): void {
    $this->withoutMockingConsoleOutput();
    Artisan::call('data:status', ['--json' => true]);

    expect(trim(Artisan::output()))->toBe('[]');
});

it('applies the pending filter to json output', function (): void {
    insertDataMigrationRecord('vanished', 1, 'completed');
    $file = $this->createTestMigration('still_pending', dataMigrationContent());

    $this->withoutMockingConsoleOutput();
    Artisan::call('data:status', ['--json' => true, '--pending' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($json)->toHaveCount(1)
        ->and($json[0]['name'])->toBe(basename($file, '.php'))
        ->and($json[0]['status'])->toBe('pending');
});
