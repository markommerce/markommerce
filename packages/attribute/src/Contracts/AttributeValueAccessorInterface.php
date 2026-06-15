<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Contracts;

use Markommerce\Attribute\Exceptions\AttributeDefinitionNotFoundException;
use Markommerce\Attribute\Exceptions\InvalidAttributeOptionException;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;

interface AttributeValueAccessorInterface
{
    /**
     * @throws AttributeDefinitionNotFoundException|InvalidAttributeValueException|InvalidAttributeOptionException|UnknownAttributeTypeException
     */
    public function set(object $entity, string $code, mixed $raw): void;

    /**
     * @throws AttributeDefinitionNotFoundException|UnknownAttributeTypeException
     */
    public function get(object $entity, string $code): mixed;

    /**
     * @return array<string, mixed>
     */
    public function all(object $entity): array;

    /**
     * @throws AttributeDefinitionNotFoundException
     */
    public function clear(object $entity, string $code): void;
}
