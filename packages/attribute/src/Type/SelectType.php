<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Type;

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeOptionException;

readonly class SelectType implements AttributeTypeInterface
{
    public function code(): string
    {
        return 'select';
    }

    /**
     * @throws InvalidAttributeOptionException
     */
    public function cast(
        mixed $raw,
        AttributeDefinitionInterface $definition,
    ): mixed
    {
        $options = $definition->config()['options'] ?? [];

        if (!array_any($options, fn (string $option) => $option === $raw)) {
            throw InvalidAttributeOptionException::forValue($definition->code(), (string) $raw);
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
