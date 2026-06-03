<?php

declare(strict_types=1);

namespace Markommerce\Money;

use Markommerce\Money\Exceptions\InvalidCurrencyException;

readonly class Currency
{
    public string $code;

    /**
     * @throws InvalidCurrencyException
     */
    public function __construct(
        string $code,
        public int $scale,
        public string $symbol,
        public string $name,
    ) {
        $uppercased = strtoupper($code);

        if (!preg_match('/^[A-Z]{3}$/', $uppercased)) {
            throw InvalidCurrencyException::forInvalidCode($code);
        }

        if ($scale < 0) {
            throw InvalidCurrencyException::forNegativeScale($scale);
        }

        $this->code = $uppercased;
    }

    public function equals(Currency $currency): bool
    {
        return $this->code === $currency->code;
    }
}
