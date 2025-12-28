<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Vherbaut\DataMigrations\DataMigrationsServiceProvider;

/**
 * Base test case for package tests.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Get package providers.
     *
     * @param Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DataMigrationsServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('data-migrations.path', $this->getTestMigrationPath());
        $app['config']->set('data-migrations.table', 'data_migrations');
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Get the test migration path.
     *
     * @return string
     */
    protected function getTestMigrationPath(): string
    {
        $path = __DIR__.'/fixtures/migrations';

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Clean up test fixtures.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cleanupTestMigrations();
        parent::tearDown();
    }

    /**
     * Remove test migration files.
     *
     * @return void
     */
    protected function cleanupTestMigrations(): void
    {
        $path = $this->getTestMigrationPath();

        if (is_dir($path)) {
            $files = glob($path.'/*.php');

            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Create a test migration file.
     *
     * @param string $name
     * @param string $content
     * @return string
     */
    protected function createTestMigration(string $name, string $content): string
    {
        $path = $this->getTestMigrationPath();
        $filename = date('Y_m_d_His').'_'.$name.'.php';
        $filepath = $path.'/'.$filename;

        file_put_contents($filepath, $content);

        return $filepath;
    }
}
