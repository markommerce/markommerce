<?php

declare(strict_types=1);

namespace Markommerce\Money;

use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\Exceptions\UnknownCurrencyException;

class DefaultCurrencyRegistry implements CurrencyRegistryInterface
{
    private const string DATA_FILE = __DIR__ . '/../resources/iso4217.php';

    /** @var array<string, array{scale: int, symbol: string, name: string}> */
    private array $data;

    /** @var array<string, Currency> */
    private array $built = [];

    public function __construct(?string $dataFile = null)
    {
        /** @var array<string, array{scale: int, symbol: string, name: string}> $data */
        $data = require($dataFile ?? self::DATA_FILE);
        $this->data = $data;
    }

    /**
     * @throws UnknownCurrencyException
     */
    public function get(string $code): Currency
    {
        $uppercased = strtoupper($code);

        if (!isset($this->data[$uppercased])) {
            throw UnknownCurrencyException::forCode($code);
        }

        return $this->built[$uppercased] ??= new Currency(
            code: $uppercased,
            scale: $this->data[$uppercased]['scale'],
            symbol: $this->data[$uppercased]['symbol'],
            name: $this->data[$uppercased]['name'],
        );
    }

    public function has(string $code): bool
    {
        return isset($this->data[strtoupper($code)]);
    }

    /**
     * @return array<string, Currency>
     */
    public function all(): array
    {
        foreach (array_keys($this->data) as $code) {
            $this->get($code);
        }

        return $this->built;
    }
}
