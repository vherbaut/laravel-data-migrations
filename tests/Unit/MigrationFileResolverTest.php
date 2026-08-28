<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Vherbaut\DataMigrations\Exceptions\InvalidMigrationException;
use Vherbaut\DataMigrations\Exceptions\MigrationNotFoundException;
use Vherbaut\DataMigrations\Migration\DataMigration;
use Vherbaut\DataMigrations\Migration\MigrationFileResolver;

class ResolverHomonymMigration extends DataMigration
{
    protected string $description = 'declared in the test file';

    public function up(): void {}
}

beforeEach(function (): void {
    $this->filesystem = new Filesystem;
    $this->testPath = __DIR__.'/../fixtures/migrations';
    $this->altPath = __DIR__.'/../fixtures/alt';

    foreach ([$this->testPath, $this->altPath] as $path) {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
});

afterEach(function (): void {
    foreach ([$this->testPath, $this->altPath] as $path) {
        $files = glob($path.'/*.php');

        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    rmdir($this->altPath);
});

it('returns empty array when directory does not exist', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, '/nonexistent/path');

    expect($resolver->getMigrationFiles())->toBe([]);
});

it('gets migration files from directory', function (): void {
    file_put_contents($this->testPath.'/2024_01_01_000000_first_migration.php', '<?php return new class {};');
    file_put_contents($this->testPath.'/2024_01_02_000000_second_migration.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $files = $resolver->getMigrationFiles();

    expect($files)->toHaveCount(2);
});

it('sorts migration files by name', function (): void {
    file_put_contents($this->testPath.'/2024_01_02_000000_second_migration.php', '<?php return new class {};');
    file_put_contents($this->testPath.'/2024_01_01_000000_first_migration.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $files = $resolver->getMigrationFiles();

    expect(basename($files[0]))->toBe('2024_01_01_000000_first_migration.php')
        ->and(basename($files[1]))->toBe('2024_01_02_000000_second_migration.php');
});

it('extracts migration name from file path', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    $name = $resolver->getMigrationName('/path/to/2024_01_01_000000_test_migration.php');

    expect($name)->toBe('2024_01_01_000000_test_migration');
});

it('extracts migration class name from file path', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    $class = $resolver->getMigrationClass('/path/to/2024_01_01_000000_update_user_emails.php');

    expect($class)->toBe('UpdateUserEmails');
});

it('finds migration file by name', function (): void {
    file_put_contents($this->testPath.'/2024_01_01_000000_target_migration.php', '<?php return new class {};');
    file_put_contents($this->testPath.'/2024_01_02_000000_other_migration.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $file = $resolver->findMigrationFile('2024_01_01_000000_target_migration');

    expect($file)->toEndWith('2024_01_01_000000_target_migration.php');
});

it('returns null when migration file not found', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $file = $resolver->findMigrationFile('nonexistent_migration');

    expect($file)->toBeNull();
});

it('globs every configured path sorted by basename', function (): void {
    file_put_contents($this->testPath.'/2024_01_02_000000_second.php', '<?php return new class {};');
    file_put_contents($this->altPath.'/2024_01_01_000000_first.php', '<?php return new class {};');
    file_put_contents($this->altPath.'/2024_01_03_000000_third.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $resolver->setPaths([$this->testPath, $this->altPath]);

    expect(array_map('basename', $resolver->getMigrationFiles()))
        ->toBe(['2024_01_01_000000_first.php', '2024_01_02_000000_second.php', '2024_01_03_000000_third.php'])
        ->and($resolver->getPaths())->toBe([$this->testPath, $this->altPath]);
});

it('ignores missing directories among the paths', function (): void {
    file_put_contents($this->testPath.'/2024_01_01_000000_only.php', '<?php return new class {};');

    $resolver = new MigrationFileResolver($this->filesystem, '/nonexistent/default');
    $resolver->setPaths(['/nonexistent/path', $this->testPath]);

    expect($resolver->getMigrationFiles())->toHaveCount(1);
});

it('uses the default path until paths are set and restores it with an empty list', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    expect($resolver->getPaths())->toBe([$this->testPath]);

    $resolver->setPaths(['/somewhere']);

    expect($resolver->getPaths())->toBe(['/somewhere']);

    $resolver->setPaths([]);

    expect($resolver->getPaths())->toBe([$this->testPath]);
});

it('throws MigrationNotFoundException when the file does not exist', function (): void {
    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    expect(fn () => $resolver->resolve($this->testPath.'/2024_01_01_000000_missing.php'))
        ->toThrow(MigrationNotFoundException::class);
});

it('throws InvalidMigrationException when the file does not provide a migration', function (): void {
    $file = $this->testPath.'/2024_01_01_000000_not_a_migration.php';
    file_put_contents($file, "<?php\n\nreturn 42;\n");

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    expect(fn () => $resolver->resolve($file))->toThrow(InvalidMigrationException::class);
});

it('throws InvalidMigrationException when the file returns an object that is not a migration', function (): void {
    $file = $this->testPath.'/2024_01_01_000000_foreign_object.php';
    file_put_contents($file, "<?php\n\nreturn new stdClass;\n");

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    expect(fn () => $resolver->resolve($file))->toThrow(InvalidMigrationException::class);
});

it('resolves a named migration class declared in the file', function (): void {
    $file = $this->testPath.'/2024_01_01_000000_resolver_named_migration.php';
    file_put_contents($file, <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

class ResolverNamedMigration extends DataMigration
{
    public function up(): void {}
}
PHP);

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $first = $resolver->resolve($file);
    $second = $resolver->resolve($file);

    expect($first)->toBeInstanceOf(ResolverNamedMigration::class)
        ->and($second)->toBeInstanceOf(ResolverNamedMigration::class)
        ->and($second)->not->toBe($first);
});

it('does not instantiate an application class that merely shares the migration name', function (): void {
    $file = $this->testPath.'/2024_01_01_000000_resolver_homonym_migration.php';
    file_put_contents($file, <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

return new class extends DataMigration
{
    protected string $description = 'declared in the migration file';

    public function up(): void {}
};
PHP);

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);

    expect($resolver->resolve($file)->getDescription())->toBe('declared in the migration file');
});

it('includes an anonymous class file once per resolution', function (): void {
    unset($GLOBALS['resolver_include_count']);
    $file = $this->testPath.'/2024_01_01_000000_counted_include.php';
    file_put_contents($file, <<<'PHP'
<?php

use Vherbaut\DataMigrations\Migration\DataMigration;

$GLOBALS['resolver_include_count'] = ($GLOBALS['resolver_include_count'] ?? 0) + 1;

return new class extends DataMigration
{
    public function up(): void {}
};
PHP);

    $resolver = new MigrationFileResolver($this->filesystem, $this->testPath);
    $first = $resolver->resolve($file);
    $second = $resolver->resolve($file);

    expect($GLOBALS['resolver_include_count'])->toBe(2)
        ->and($second)->not->toBe($first);
});
