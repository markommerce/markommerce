<?php

declare(strict_types=1);

namespace Markommerce\Money;

/**
 * Provides the application's default currency code (ISO 4217).
 *
 * @todo multi-store: the stores module will rebind this contract to a store-scoped resolver,
 *       so that each store can return its own configured default currency.
 */
interface CurrencyConfigInterface
{
    public function getDefault(): string;
}
