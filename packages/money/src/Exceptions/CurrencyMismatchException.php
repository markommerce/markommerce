<?php

declare(strict_types=1);

namespace Markommerce\Money\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Money\Currency;

class CurrencyMismatchException extends MarkoException
{
    public static function forMismatch(
        Currency $expected,
        Currency $actual,
    ): self {
        return new self(
            message: "Cannot mix '$expected->code' and '$actual->code' currencies in a single operation.",
            context: 'Performing arithmetic or comparison on two Money instances — both must share the same currency.',
            suggestion: "Convert one amount to '$expected->code' before operating on it.",
        );
    }
}
