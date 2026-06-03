<?php

declare(strict_types=1);

namespace Markommerce\Money\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidCurrencyException extends MarkoException
{
    public static function forInvalidCode(string $code): self
    {
        return new self(
            message: "Currency code '$code' is not a valid ISO 4217 code.",
            context: 'Creating a Currency value object — code must be exactly 3 uppercase A–Z letters.',
            suggestion: "Provide a valid ISO 4217 code such as 'USD', 'EUR', or 'JPY'.",
        );
    }

    public static function forNegativeScale(int $scale): self
    {
        return new self(
            message: "Currency scale '$scale' must be zero or greater.",
            context: 'Creating a Currency value object — scale represents the number of decimal places.',
            suggestion: 'Provide a non-negative integer for scale, e.g. 2 for USD or 0 for JPY.',
        );
    }
}
