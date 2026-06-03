<?php

declare(strict_types=1);

namespace Markommerce\Money\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DivisionByZeroException extends MarkoException
{
    public static function forDivisionByZero(): self
    {
        return new self(
            message: 'Cannot divide a Money amount by zero.',
            context: 'Dividing a Money instance — the divisor must be a non-zero value.',
            suggestion: 'Ensure the divisor is a non-zero string or integer before calling divide().',
        );
    }
}
