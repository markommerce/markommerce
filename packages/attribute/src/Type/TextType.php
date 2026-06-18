<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Type;

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;

readonly class TextType implements AttributeTypeInterface
{
    public function code(): string
    {
        return 'text';
    }

    /**
     * @throws InvalidAttributeValueException
     */
    public function cast(
        mixed $raw,
        AttributeDefinitionInterface $definition,
    ): mixed {
        if (!is_string($raw)) {
            throw InvalidAttributeValueException::forValue($definition->code(), $this->code(), (string) $raw);
        }

        return $raw;
    }

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function deserialize(mixed $stored): mixed
    {
        return $stored;
    }

    public function facetKind(): FacetKind
    {
        return FacetKind::Term;
    }
}
