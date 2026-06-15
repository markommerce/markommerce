<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class OptionsNotAllowedException extends MarkoException
{
    public static function forType(string $typeCode): self
    {
        return new self(
            message: "Options are not allowed for attribute type '$typeCode'",
            context: "Attaching options to an attribute of type '$typeCode' — only 'select' and 'multiselect' attributes support options",
            suggestion: "Remove the options or change the attribute type to 'select' or 'multiselect'",
        );
    }
}
