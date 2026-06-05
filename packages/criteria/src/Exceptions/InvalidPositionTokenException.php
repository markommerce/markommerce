<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidPositionTokenException extends MarkoException
{
    public static function malformed(string $reason): self
    {
        return new self(
            message: "Position token is malformed: $reason",
            context: 'While decoding a pagination position token',
            suggestion: 'Restart from the first page to obtain a valid position token',
        );
    }

    public static function unsupportedVersion(int $version): self
    {
        return new self(
            message: "Position token uses unsupported format version $version",
            context: 'While decoding a pagination position token',
            suggestion: 'Restart from the first page to obtain a token with a supported format version',
        );
    }
}
