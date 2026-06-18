<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Type;

use DateTimeImmutable;
use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;

readonly class DateType implements AttributeTypeInterface
{
    private const string CANONICAL_FORMAT = 'Y-m-d';

    public function code(): string
    {
        return 'date';
    }

    /**
     * @throws InvalidAttributeValueException
     */
    public function cast(
        mixed $raw,
        AttributeDefinitionInterface $definition,
    ): mixed {
        if ($raw instanceof DateTimeImmutable) {
            return $raw->format(self::CANONICAL_FORMAT);
        }

        if (!is_string($raw)) {
            throw InvalidAttributeValueException::forValue($definition->code(), $this->code(), (string) $raw);
        }

        $date = DateTimeImmutable::createFromFormat(self::CANONICAL_FORMAT, $raw);

        if ($date === false || $date->format(self::CANONICAL_FORMAT) !== $raw) {
            throw InvalidAttributeValueException::forValue($definition->code(), $this->code(), $raw);
        }

        return $date->format(self::CANONICAL_FORMAT);
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
