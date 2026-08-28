<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Output;

use Vherbaut\DataMigrations\Contracts\MigrationOutput;

/**
 * Keeps the messages and the progress in memory, for tests and programmatic runs.
 */
class MemoryOutput implements MigrationOutput
{
    /** @var array<int, string> */
    protected array $lines = [];

    protected int $progressTotal = 0;

    protected int $progressCurrent = 0;

    /**
     * @param string $message
     * @return void
     */
    public function line(string $message): void
    {
        $this->lines[] = $message;
    }

    /**
     * @param string $message
     * @return void
     */
    public function info(string $message): void
    {
        $this->lines[] = $message;
    }

    /**
     * @param string $message
     * @return void
     */
    public function warn(string $message): void
    {
        $this->lines[] = $message;
    }

    /**
     * @param string $message
     * @return void
     */
    public function error(string $message): void
    {
        $this->lines[] = $message;
    }

    /**
     * @param int $total
     * @param string $message
     * @return void
     */
    public function startProgress(int $total, string $message = ''): void
    {
        $this->progressTotal = $total;
        $this->progressCurrent = 0;

        if ($message !== '') {
            $this->lines[] = $message;
        }
    }

    /**
     * @param int $step
     * @return void
     */
    public function advanceProgress(int $step = 1): void
    {
        $this->progressCurrent += $step;
    }

    /**
     * @param int $current
     * @return void
     */
    public function setProgress(int $current): void
    {
        $this->progressCurrent = $current;
    }

    /**
     * @return void
     */
    public function finishProgress(): void {}

    /**
     * Every message written so far, in order.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return int
     */
    public function progressTotal(): int
    {
        return $this->progressTotal;
    }

    /**
     * @return int
     */
    public function progressCurrent(): int
    {
        return $this->progressCurrent;
    }
}
