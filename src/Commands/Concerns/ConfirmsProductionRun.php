<?php

declare(strict_types=1);

namespace Vherbaut\DataMigrations\Commands\Concerns;

use Closure;
use Illuminate\Console\ConfirmableTrait;

/**
 * Asks for confirmation in production unless --force is given, through
 * Laravel's ConfirmableTrait, and honours safety.require_force_in_production.
 */
trait ConfirmsProductionRun
{
    use ConfirmableTrait;

    /**
     * Whether the confirmation is required at all.
     *
     * @return Closure
     */
    protected function getDefaultConfirmCallback(): Closure
    {
        return function (): bool {
            if (! config('data-migrations.safety.require_force_in_production', true)) {
                return false;
            }

            return $this->getLaravel()->environment('production');
        };
    }
}
