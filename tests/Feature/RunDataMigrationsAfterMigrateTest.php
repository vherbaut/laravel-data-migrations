<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->artisan('migrate');
    $this->withoutMockingConsoleOutput();

    $kernel = $this->app[Kernel::class];
    $kernel->rerouteSymfonyCommandEvents();
    $kernel->setArtisan(null);
});

it('runs pending data migrations after migrate when enabled', function (): void {
    config()->set('data-migrations.run_after_migrate', true);
    $file = $this->createTestMigration('chained', dataMigrationContent('$this->affected(1);'));

    $this->artisan('migrate');

    expect(DB::table('data_migrations')->where('status', 'completed')->pluck('migration')->all())
        ->toBe([basename($file, '.php')]);
});

it('does nothing when disabled', function (): void {
    $this->createTestMigration('not_chained', dataMigrationContent());

    $this->artisan('migrate');

    expect(DB::table('data_migrations')->count())->toBe(0);
});

it('does nothing after migrate --pretend', function (): void {
    config()->set('data-migrations.run_after_migrate', true);
    $this->createTestMigration('pretended', dataMigrationContent());

    $this->artisan('migrate', ['--pretend' => true]);

    expect(DB::table('data_migrations')->count())->toBe(0);
});

it('does nothing after other migrate commands', function (): void {
    config()->set('data-migrations.run_after_migrate', true);
    $this->createTestMigration('status_only', dataMigrationContent());

    $this->artisan('migrate:status');

    expect(DB::table('data_migrations')->count())->toBe(0);
});

it('runs after migrate:refresh', function (): void {
    config()->set('data-migrations.run_after_migrate', true);
    $file = $this->createTestMigration('after_refresh', dataMigrationContent());

    $this->artisan('migrate:refresh');

    expect(DB::table('data_migrations')->where('status', 'completed')->pluck('migration')->all())
        ->toBe([basename($file, '.php')]);
});

it('does nothing when the migrate command failed', function (): void {
    config()->set('data-migrations.run_after_migrate', true);
    $this->createTestMigration('after_failure', dataMigrationContent());
    $schemaPath = __DIR__.'/../fixtures/schema';
    mkdir($schemaPath, 0755, true);
    file_put_contents($schemaPath.'/2024_01_01_000000_failing_schema.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        throw new RuntimeException('schema boom');
    }
};
PHP);

    try {
        expect(fn () => $this->artisan('migrate', ['--path' => $schemaPath, '--realpath' => true]))
            ->toThrow(RuntimeException::class, 'schema boom')
            ->and(DB::table('data_migrations')->count())->toBe(0);
    } finally {
        unlink($schemaPath.'/2024_01_01_000000_failing_schema.php');
        rmdir($schemaPath);
    }
});
