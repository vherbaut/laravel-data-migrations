<?php

declare(strict_types=1);

use Vherbaut\DataMigrations\Migration\DataMigration;
use Vherbaut\DataMigrations\Output\MemoryOutput;

function progressTestMigration(): DataMigration
{
    return new class extends DataMigration
    {
        public function up(): void {}

        public function track(int $total): float
        {
            $this->startProgress($total, 'Tracking');
            $this->incrementProgress();
            $this->addProgress(4);
            $this->finishProgress();

            return $this->getProgressPercentage();
        }

        public function jumpTo(int $current): void
        {
            $this->startProgress(10);
            $this->setProgress($current);
        }

        public function report(string $message): void
        {
            $this->info($message);
            $this->warn($message);
            $this->error($message);
        }
    };
}

it('forwards the progress to the output', function (): void {
    $output = new MemoryOutput;
    $migration = progressTestMigration();
    $migration->setOutput($output);

    $percentage = $migration->track(10);

    expect($percentage)->toBe(50.0)
        ->and($output->progressTotal())->toBe(10)
        ->and($output->progressCurrent())->toBe(5)
        ->and($output->lines())->toBe(['Tracking']);
});

it('sets the progress to a given value', function (): void {
    $output = new MemoryOutput;
    $migration = progressTestMigration();
    $migration->setOutput($output);

    $migration->jumpTo(7);

    expect($output->progressCurrent())->toBe(7);
});

it('tracks the progress silently without output', function (): void {
    $migration = progressTestMigration();

    expect($migration->track(4))->toBe(125.0);
});

it('forwards the messages to the output', function (): void {
    $output = new MemoryOutput;
    $migration = progressTestMigration();
    $migration->setOutput($output);

    $migration->report('hello');

    expect($output->lines())->toBe(['hello', 'hello', 'hello']);
});
