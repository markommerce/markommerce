<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class AttributeDefinitionNotFoundException extends MarkoException
{
    public static function forCode(
        string $entityType,
        string $code,
    ): self
    {
        return new self(
            message: "Attribute definition '$code' not found for entity type '$entityType'",
            context: "Looking up attribute definition '$code' for entity type '$entityType' — no definition is registered with this code",
            suggestion: "Ensure the attribute '$code' is defined for entity type '$entityType' before attempting to use it",
        );
    }
}
