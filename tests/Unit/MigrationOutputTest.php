<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Vherbaut\DataMigrations\Output\ConsoleOutput;
use Vherbaut\DataMigrations\Output\MemoryOutput;
use Vherbaut\DataMigrations\Output\NullOutput;

it('keeps the messages and the progress in memory', function (): void {
    $output = new MemoryOutput;

    $output->line('plain');
    $output->info('green');
    $output->warn('yellow');
    $output->error('red');
    $output->startProgress(10, 'Working');
    $output->advanceProgress(3);
    $output->setProgress(7);
    $output->finishProgress();

    expect($output->lines())->toBe(['plain', 'green', 'yellow', 'red', 'Working'])
        ->and($output->progressTotal())->toBe(10)
        ->and($output->progressCurrent())->toBe(7);
});

it('discards everything in the null output', function (): void {
    $output = new NullOutput;

    $output->line('plain');
    $output->info('green');
    $output->warn('yellow');
    $output->error('red');
    $output->startProgress(10, 'Working');
    $output->advanceProgress(3);
    $output->setProgress(7);
    $output->finishProgress();

    expect(true)->toBeTrue();
});

it('writes styled lines and a progress bar to the console', function (): void {
    $buffer = new BufferedOutput;
    $output = new ConsoleOutput(new OutputStyle(new ArrayInput([]), $buffer));

    $output->line('plain');
    $output->info('green');
    $output->warn('yellow');
    $output->error('red <tag>');
    $output->startProgress(4, 'Working');
    $output->advanceProgress(2);
    $output->setProgress(4);
    $output->finishProgress();
    $output->advanceProgress(1);

    $written = $buffer->fetch();

    expect($written)->toContain('plain')
        ->and($written)->toContain('green')
        ->and($written)->toContain('yellow')
        ->and($written)->toContain('red <tag>')
        ->and($written)->toContain('Working')
        ->and($written)->toContain('4/4');
});
