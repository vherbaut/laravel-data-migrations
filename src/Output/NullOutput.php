<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Output;

use Vherbaut\DataMigrations\Contracts\MigrationOutput;

/**
 * Discards every message and progress update.
 */
class NullOutput implements MigrationOutput
{
    /**
     * @param string $message
     * @return void
     */
    public function line(string $message): void {}

    /**
     * @param string $message
     * @return void
     */
    public function info(string $message): void {}

    /**
     * @param string $message
     * @return void
     */
    public function warn(string $message): void {}

    /**
     * @param string $message
     * @return void
     */
    public function error(string $message): void {}

    /**
     * @param int $total
     * @param string $message
     * @return void
     */
    public function startProgress(int $total, string $message = ''): void {}

    /**
     * @param int $step
     * @return void
     */
    public function advanceProgress(int $step = 1): void {}

    /**
     * @param int $current
     * @return void
     */
    public function setProgress(int $current): void {}

    /**
     * @return void
     */
    public function finishProgress(): void {}
}
