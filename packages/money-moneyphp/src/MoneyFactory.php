<?php

declare(strict_types=1);

namespace Markommerce\Money\Moneyphp;

use Markommerce\Money\CurrencyConfigInterface;
use Markommerce\Money\MoneyFactoryInterface;
use Markommerce\Money\MoneyInterface;

class MoneyFactory implements MoneyFactoryInterface
{
    public function __construct(private CurrencyConfigInterface $currencyConfig) {}

    public function create(int $amount, ?string $currency = null): MoneyInterface
    {
        $resolved = $currency ?? $this->currencyConfig->getDefault();

        /** @var non-empty-string $resolved */
        return new Money($amount, $resolved);
    }
}
