<?php

declare(strict_types=1);

use Vherbaut\DataMigrations\Contracts\Reversible;
use Vherbaut\DataMigrations\Migration\DataMigration;

it('reports a migration implementing Reversible as reversible in dryRun', function (): void {
    $migration = new class extends DataMigration implements Reversible
    {
        public function up(): void {}

        public function down(): void {}
    };

    expect($migration->dryRun()['reversible'])->toBeTrue();
});

it('reports a migration declaring down() without Reversible as not reversible in dryRun', function (): void {
    $migration = new class extends DataMigration
    {
        public function up(): void {}

        public function down(): void {}
    };

    expect($migration->dryRun()['reversible'])->toBeFalse();
});

it('reports a migration without down() as not reversible in dryRun', function (): void {
    $migration = new class extends DataMigration
    {
        public function up(): void {}
    };

    expect($migration->dryRun()['reversible'])->toBeFalse();
});
