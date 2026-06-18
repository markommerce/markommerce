<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Type;

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;

/**
 * Validates that an attribute value is a positive integer referencing another entity by identifier.
 *
 * Phase 1 — shape validation only.
 * Out of scope: loading the referenced entity, FK enforcement, cross-entity existence checks.
 */
readonly class EntityRefType implements AttributeTypeInterface
{
    public function code(): string
    {
        return 'entityRef';
    }

    /**
     * @throws InvalidAttributeValueException
     */
    public function cast(
        mixed $raw,
        AttributeDefinitionInterface $definition,
    ): mixed {
        $config = $definition->config();

        if (!isset($config['targetEntityType'])) {
            throw new InvalidAttributeValueException(
                message: "Cannot cast entityRef attribute '{$definition->code()}': no target entity type is configured",
                context: "Casting attribute '{$definition->code()}' (type: entityRef) — the 'targetEntityType' key is missing from the attribute config",
                suggestion: "Set 'targetEntityType' in the config for attribute '{$definition->code()}' to declare which entity type this reference points to",
            );
        }

        if (!is_int($raw)) {
            throw InvalidAttributeValueException::forValue($definition->code(), $this->code(), (string) $raw);
        }

        if ($raw <= 0) {
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
        return is_int($stored) ? $stored : (int) $stored;
    }

    public function facetKind(): FacetKind
    {
        return FacetKind::Term;
    }
}
