<?php

declare(strict_types=1);

namespace Markommerce\Money;

interface MoneyFactoryInterface
{
    public function create(int $amount, ?string $currency = null): MoneyInterface;
}
