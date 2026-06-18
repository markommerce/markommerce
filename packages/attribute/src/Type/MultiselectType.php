<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Type;

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeOptionException;

readonly class MultiselectType implements AttributeTypeInterface
{
    public function code(): string
    {
        return 'multiselect';
    }

    /**
     * @throws InvalidAttributeOptionException
     */
    public function cast(
        mixed $raw,
        AttributeDefinitionInterface $definition,
    ): mixed {
        if (!is_array($raw)) {
            throw InvalidAttributeOptionException::forValue($definition->code(), (string) $raw);
        }

        $options = $definition->config()['options'] ?? [];

        foreach ($raw as $value) {
            if (!array_any($options, fn (string $option) => $option === $value)) {
                throw InvalidAttributeOptionException::forValue($definition->code(), (string) $value);
            }
        }

        return $raw;
    }

    public function serialize(mixed $value): mixed
    {
        return json_encode($value);
    }

    public function deserialize(mixed $stored): mixed
    {
        return json_decode((string) $stored, true);
    }

    public function facetKind(): FacetKind
    {
        return FacetKind::Term;
    }
}
