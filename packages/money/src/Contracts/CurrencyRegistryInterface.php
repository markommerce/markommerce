<?php

declare(strict_types=1);

namespace Markommerce\Money\Contracts;

use Markommerce\Money\Currency;
use Markommerce\Money\Exceptions\UnknownCurrencyException;

interface CurrencyRegistryInterface
{
    /**
     * @throws UnknownCurrencyException
     */
    public function get(string $code): Currency;

    public function has(string $code): bool;

    /**
     * @return array<string, Currency>
     */
    public function all(): array;
}
