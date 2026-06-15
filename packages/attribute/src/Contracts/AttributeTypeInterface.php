<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Contracts;

use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Type\FacetKind;

interface AttributeTypeInterface
{
    public function code(): string;

    /**
     * @throws InvalidAttributeValueException
     */
    public function cast(
        mixed $raw,
        AttributeDefinitionInterface $definition,
    ): mixed;

    public function serialize(mixed $value): mixed;

    public function deserialize(mixed $stored): mixed;

    public function facetKind(): FacetKind;
}
