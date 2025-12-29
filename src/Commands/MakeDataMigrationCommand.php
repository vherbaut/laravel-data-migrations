<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Command to create a new data migration file.
 */
class MakeDataMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:data-migration
                            {name : The name of the data migration}
                            {--table= : The table to migrate}
                            {--chunked : Create a chunked migration template}
                            {--idempotent : Mark the migration as idempotent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new data migration file';

    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        /** @var string $nameArgument */
        $nameArgument = $this->argument('name');
        $name = Str::snake(str_replace(' ', '_', trim($nameArgument)));

        if (! $this->validateName($name)) {
            return self::FAILURE;
        }

        /** @var string $path */
        $path = config('data-migrations.path');

        $this->ensureDirectoryExists($path);

        $filename = date('Y_m_d_His').'_'.$name.'.php';
        $filepath = $path.'/'.$filename;

        if ($this->files->exists($filepath)) {
            $this->error("Data migration already exists: {$filename}");

            return self::FAILURE;
        }

        try {
            $stub = $this->getStub();
            $content = $this->populateStub($stub, $name);
            $this->files->put($filepath, $content);
        } catch (RuntimeException $e) {
            $this->error("Failed to create migration: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Created data migration: {$filename}");
        $this->line("Path: {$filepath}");

        return self::SUCCESS;
    }

    /**
     * Validate the migration name.
     *
     * @param string $name
     * @return bool
     */
    protected function validateName(string $name): bool
    {
        if ($name === '') {
            $this->error('Migration name cannot be empty.');

            return false;
        }

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $this->error('Migration name must start with a letter and contain only lowercase letters, numbers, and underscores.');

            return false;
        }

        if (strlen($name) > 100) {
            $this->error('Migration name is too long (max 100 characters).');

            return false;
        }

        return true;
    }

    /**
     * Ensure the migration directory exists.
     *
     * @param string $path
     * @return void
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Get the stub file content.
     *
     * @return string
     * @throws RuntimeException
     */
    protected function getStub(): string
    {
        $stubName = $this->option('chunked') ? 'data-migration.chunked.stub' : 'data-migration.stub';

        $publishedStub = base_path("stubs/{$stubName}");

        if ($this->files->exists($publishedStub)) {
            return $this->files->get($publishedStub);
        }

        $packageStub = __DIR__."/../../stubs/{$stubName}";

        return $this->files->get($packageStub);
    }

    /**
     * Populate the stub with values.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function populateStub(string $stub, string $name): string
    {
        /** @var string|null $tableOption */
        $tableOption = $this->option('table');
        $table = $tableOption ?? 'your_table';

        $className = Str::studly($name);
        $description = Str::headline($name);
        $idempotent = $this->option('idempotent') ? 'true' : 'false';

        return str_replace(
            ['{{ class }}', '{{ table }}', '{{ description }}', '{{ idempotent }}'],
            [$className, $table, $description, $idempotent],
            $stub
        );
    }
}
