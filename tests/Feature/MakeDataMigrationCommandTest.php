<?php

declare(strict_types=1);

it('creates a new data migration file', function (): void {
    $this->artisan('make:data-migration', ['name' => 'update_user_emails'])
        ->expectsOutputToContain('Created data migration:')
        ->assertSuccessful();

    $files = glob($this->getTestMigrationPath().'/*update_user_emails.php');
    expect($files)->toHaveCount(1);
});

it('creates a chunked migration template', function (): void {
    $this->artisan('make:data-migration', [
        'name' => 'chunked_migration',
        '--chunked' => true,
    ])
        ->assertSuccessful();

    $files = glob($this->getTestMigrationPath().'/*chunked_migration.php');
    expect($files)->toHaveCount(1);

    $content = file_get_contents($files[0]);
    expect($content)->toContain('chunk');
});

it('rejects empty migration name', function (): void {
    $this->artisan('make:data-migration', ['name' => '   '])
        ->expectsOutputToContain('Migration name cannot be empty')
        ->assertFailed();
});

it('rejects invalid migration name', function (): void {
    $this->artisan('make:data-migration', ['name' => '123_invalid'])
        ->expectsOutputToContain('Migration name must start with a letter')
        ->assertFailed();
});

it('rejects too long migration name', function (): void {
    $longName = str_repeat('a', 101);

    $this->artisan('make:data-migration', ['name' => $longName])
        ->expectsOutputToContain('Migration name is too long')
        ->assertFailed();
});

it('creates migrations with unique timestamps', function (): void {
    $this->artisan('make:data-migration', ['name' => 'first_test'])
        ->assertSuccessful();

    sleep(1); // Ensure different timestamp

    $this->artisan('make:data-migration', ['name' => 'second_test'])
        ->assertSuccessful();

    $files = glob($this->getTestMigrationPath().'/*.php');
    expect($files)->toHaveCount(2);
});

it('sets table name in migration', function (): void {
    $this->artisan('make:data-migration', [
        'name' => 'with_table',
        '--table' => 'custom_table',
    ])
        ->assertSuccessful();

    $files = glob($this->getTestMigrationPath().'/*with_table.php');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('custom_table');
});

it('marks migration as idempotent when flag is set', function (): void {
    $this->artisan('make:data-migration', [
        'name' => 'idempotent_migration',
        '--idempotent' => true,
    ])
        ->assertSuccessful();

    $files = glob($this->getTestMigrationPath().'/*idempotent_migration.php');
    $content = file_get_contents($files[0]);

    expect($content)->toContain('idempotent = true');
});
