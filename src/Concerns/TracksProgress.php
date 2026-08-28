<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Concerns;

use Vherbaut\DataMigrations\Contracts\MigrationOutput;

/**
 * Counts the progress of a data migration and forwards it to its output.
 */
trait TracksProgress
{
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
     * The output receiving the progress.
     *
     * @return MigrationOutput
     */
    abstract protected function output(): MigrationOutput;

    /**
     * Start tracking a number of items.
     *
     * @param int $total
     * @param string $message
     * @return void
     */
    protected function startProgress(int $total, string $message = 'Processing...'): void
    {
        $this->progressTotal = $total;
        $this->progressCurrent = 0;

        $this->output()->startProgress($total, $message);
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

        $this->output()->advanceProgress($amount);
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

        $this->output()->setProgress($current);
    }

    /**
     * Finish the progress tracking.
     *
     * @return void
     */
    protected function finishProgress(): void
    {
        $this->output()->finishProgress();
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
