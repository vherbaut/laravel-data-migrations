<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Output;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressBar;
use Vherbaut\DataMigrations\Contracts\MigrationOutput;

/**
 * Renders messages as styled console lines and the progress as a progress bar.
 */
class ConsoleOutput implements MigrationOutput
{
    protected ?ProgressBar $progressBar = null;

    /**
     * @param OutputStyle $output
     */
    public function __construct(protected OutputStyle $output) {}

    /**
     * @param string $message
     * @return void
     */
    public function line(string $message): void
    {
        $this->output->writeln(OutputFormatter::escape($message));
    }

    /**
     * @param string $message
     * @return void
     */
    public function info(string $message): void
    {
        $this->styled('info', $message);
    }

    /**
     * @param string $message
     * @return void
     */
    public function warn(string $message): void
    {
        $this->styled('comment', $message);
    }

    /**
     * @param string $message
     * @return void
     */
    public function error(string $message): void
    {
        $this->styled('error', $message);
    }

    /**
     * @param int $total
     * @param string $message
     * @return void
     */
    public function startProgress(int $total, string $message = ''): void
    {
        if ($message !== '') {
            $this->line($message);
        }

        $this->progressBar = $this->output->createProgressBar($total);
        $this->progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $this->progressBar->start();
    }

    /**
     * @param int $step
     * @return void
     */
    public function advanceProgress(int $step = 1): void
    {
        $this->progressBar?->advance($step);
    }

    /**
     * @param int $current
     * @return void
     */
    public function setProgress(int $current): void
    {
        $this->progressBar?->setProgress($current);
    }

    /**
     * @return void
     */
    public function finishProgress(): void
    {
        if ($this->progressBar === null) {
            return;
        }

        $this->progressBar->finish();
        $this->output->newLine(2);
        $this->progressBar = null;
    }

    /**
     * @param string $style
     * @param string $message
     * @return void
     */
    protected function styled(string $style, string $message): void
    {
        $escaped = OutputFormatter::escape($message);

        $this->output->writeln("<{$style}>{$escaped}</{$style}>");
    }
}
