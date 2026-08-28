<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Contracts;

/**
 * Where the migrator and the migrations write their messages and progress.
 * Implementations decide how to render them (console, memory, nowhere).
 */
interface MigrationOutput
{
    /**
     * @param string $message
     * @return void
     */
    public function line(string $message): void;

    /**
     * @param string $message
     * @return void
     */
    public function info(string $message): void;

    /**
     * @param string $message
     * @return void
     */
    public function warn(string $message): void;

    /**
     * @param string $message
     * @return void
     */
    public function error(string $message): void;

    /**
     * @param int $total
     * @param string $message
     * @return void
     */
    public function startProgress(int $total, string $message = ''): void;

    /**
     * @param int $step
     * @return void
     */
    public function advanceProgress(int $step = 1): void;

    /**
     * @param int $current
     * @return void
     */
    public function setProgress(int $current): void;

    /**
     * @return void
     */
    public function finishProgress(): void;
}
