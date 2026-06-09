<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class IncompatiblePositionException extends MarkoException
{
    public static function expected(
        string $strategy,
        string $tokenType,
    ): self
    {
        return new self(
            message: "Strategy '$strategy' cannot use a '$tokenType' position token",
            context: 'While resolving pagination position token for the current strategy',
            suggestion: 'Restart from the first page to obtain a compatible position token',
        );
    }
}
