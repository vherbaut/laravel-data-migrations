<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Concerns;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Provides progress tracking capabilities for data migrations.
 *
 * @property OutputStyle|null $output
 */
trait TracksProgress
{
    /**
     * The progress bar instance.
     *
     * @var ProgressBar|null
     */
    protected ?ProgressBar $progressBar = null;

    /**
     * Total items to process.
     *
     * @var int
     */
    protected int $progressTotal = 0;

    /**
     * Current progress count.
     *
     * @var int
     */
    protected int $progressCurrent = 0;

    /**
     * Start a progress bar.
     *
     * @param int $total
     * @param string $message
     * @return void
     */
    protected function startProgress(int $total, string $message = 'Processing...'): void
    {
        $this->progressTotal = $total;
        $this->progressCurrent = 0;

        if ($this->output !== null) {
            $this->output->writeln($message);
            $this->progressBar = $this->output->createProgressBar($total);
            $this->progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $this->progressBar->start();
        }
    }

    /**
     * Increment progress by 1.
     *
     * @return void
     */
    protected function incrementProgress(): void
    {
        $this->addProgress(1);
    }

    /**
     * Add to progress.
     *
     * @param int $amount
     * @return void
     */
    protected function addProgress(int $amount): void
    {
        $this->progressCurrent += $amount;

        if ($this->progressBar !== null) {
            $this->progressBar->advance($amount);
        }
    }

    /**
     * Set progress to a specific value.
     *
     * @param int $current
     * @return void
     */
    protected function setProgress(int $current): void
    {
        $this->progressCurrent = $current;

        if ($this->progressBar !== null) {
            $this->progressBar->setProgress($current);
        }
    }

    /**
     * Finish the progress bar.
     *
     * @return void
     */
    protected function finishProgress(): void
    {
        if ($this->progressBar !== null) {
            $this->progressBar->finish();

            if ($this->output !== null) {
                $this->output->newLine(2);
            }
        }

        $this->progressBar = null;
    }

    /**
     * Get current progress percentage.
     *
     * @return float
     */
    protected function getProgressPercentage(): float
    {
        if ($this->progressTotal === 0) {
            return 0.0;
        }

        return round(($this->progressCurrent / $this->progressTotal) * 100, 2);
    }
}
