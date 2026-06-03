<?php

declare(strict_types=1);

namespace Markommerce\Money;

use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\Exceptions\UnknownCurrencyException;

class DefaultCurrencyRegistry implements CurrencyRegistryInterface
{
    /** @var array<string, Currency> */
    private array $currencies;

    public function __construct()
    {
        $this->currencies = [
            'USD' => new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar'),
            'EUR' => new Currency(code: 'EUR', scale: 2, symbol: '€', name: 'Euro'),
            'GBP' => new Currency(code: 'GBP', scale: 2, symbol: '£', name: 'British Pound Sterling'),
            'JPY' => new Currency(code: 'JPY', scale: 0, symbol: '¥', name: 'Japanese Yen'),
            'PLN' => new Currency(code: 'PLN', scale: 2, symbol: 'zł', name: 'Polish Zloty'),
            'CHF' => new Currency(code: 'CHF', scale: 2, symbol: 'Fr', name: 'Swiss Franc'),
        ];
    }

    /**
     * @throws UnknownCurrencyException
     */
    public function get(string $code): Currency
    {
        $uppercased = strtoupper($code);

        if (!isset($this->currencies[$uppercased])) {
            throw UnknownCurrencyException::forCode($code);
        }

        return $this->currencies[$uppercased];
    }

    public function has(string $code): bool
    {
        return isset($this->currencies[strtoupper($code)]);
    }

    /**
     * @return array<string, Currency>
     */
    public function all(): array
    {
        return $this->currencies;
    }
}
