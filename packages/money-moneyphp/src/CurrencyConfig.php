<?php

declare(strict_types=1);

namespace Markommerce\Money\Moneyphp;

use Markommerce\Money\CurrencyConfigInterface;

/**
 * @todo multi-store: The stores/config module will rebind CurrencyConfigInterface to a store-scoped resolver. Until then this default is global.
 */
class CurrencyConfig implements CurrencyConfigInterface
{
    public function getDefault(): string
    {
        return 'USD';
    }
}
