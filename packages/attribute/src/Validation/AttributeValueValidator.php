<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Validation;

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;

class AttributeValueValidator
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {}

    /**
     * @param list<string> $allowedOptions
     * @throws InvalidAttributeValueException|UnknownAttributeTypeException
     */
    public function validate(
        AttributeDefinitionInterface $definition,
        mixed $raw,
        array $allowedOptions = [],
    ): mixed {
        $type = $this->attributeTypeRegistry->get($definition->type());

        if ($definition->isRequired() && $raw === null) {
            throw InvalidAttributeValueException::forValue($definition->code(), $definition->type(), '');
        }

        if ($raw === null) {
            return null;
        }

        $definitionView = $allowedOptions !== []
            ? new DefinitionWithOptions($definition, $allowedOptions)
            : $definition;

        return $type->cast($raw, $definitionView);
    }
}
