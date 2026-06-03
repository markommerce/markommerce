<?php

declare(strict_types=1);

namespace Markommerce\Money\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class UnknownCurrencyException extends MarkoException
{
    public static function forCode(string $code): self
    {
        return new self(
            message: "Currency '$code' is not registered in the currency registry.",
            context: 'Looking up a currency by ISO 4217 code — the code was not found in the registry.',
            suggestion: "Provide a registered currency code such as 'USD', 'EUR', or 'GBP', or register the currency in the registry.",
        );
    }
}
