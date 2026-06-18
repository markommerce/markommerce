<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Type;

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;

readonly class DecimalType implements AttributeTypeInterface
{
    public function code(): string
    {
        return 'decimal';
    }

    /**
     * @throws InvalidAttributeValueException
     */
    public function cast(
        mixed $raw,
        AttributeDefinitionInterface $definition,
    ): mixed {
        if (!is_string($raw) && !is_int($raw)) {
            throw InvalidAttributeValueException::forValue($definition->code(), $this->code(), (string) $raw);
        }

        $stringValue = (string) $raw;

        if (!is_numeric($stringValue)) {
            throw InvalidAttributeValueException::forValue($definition->code(), $this->code(), $stringValue);
        }

        $scale = $definition->config()['scale'] ?? null;

        if ($scale !== null) {
            return number_format((float) $stringValue, (int) $scale, '.', '');
        }

        if (is_int($raw)) {
            return (string) $raw;
        }

        return $stringValue;
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
        return FacetKind::Range;
    }
}
