<?php

declare(strict_types=1);

namespace Markommerce\Money;

use Marko\Core\Exceptions\MarkoException;

class MoneyException extends MarkoException
{
    public static function currencyMismatch(string $expected, string $actual, string $operation): self
    {
        return new self(
            message: "Currency mismatch during '{$operation}': expected '{$expected}', got '{$actual}'.",
            context: "Operation '{$operation}' requires both operands to share the same currency.",
            suggestion: "Convert one of the values to '{$expected}' before calling '{$operation}'.",
        );
    }

    public static function invalidAllocationRatios(string $reason): self
    {
        return new self(
            message: "Invalid allocation ratios: {$reason}.",
            context: 'The ratios array passed to allocate() must be non-empty, contain only positive values, and sum to a value greater than zero.',
            suggestion: 'Pass an array of positive numeric ratios that collectively sum to a value greater than zero.',
        );
    }

    public static function invalidMultiplyFactor(string $factor, string $reason): self
    {
        return new self(
            message: "Invalid multiply factor '{$factor}': {$reason}.",
            context: "The factor '{$factor}' passed to multiply() is not a valid numeric string.",
            suggestion: 'Pass a valid numeric string such as "1.5" or "2" to multiply().',
        );
    }
}
