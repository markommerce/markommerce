<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Registry;

use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;

class AttributeTypeRegistry
{
    /**
     * @var array<string, AttributeTypeInterface>
     */
    private array $types = [];

    public function register(AttributeTypeInterface $type): void
    {
        $this->types[$type->code()] = $type;
    }

    /**
     * @throws UnknownAttributeTypeException
     */
    public function get(string $code): AttributeTypeInterface
    {
        if (!isset($this->types[$code])) {
            throw UnknownAttributeTypeException::forType($code);
        }

        return $this->types[$code];
    }

    public function has(string $code): bool
    {
        return isset($this->types[$code]);
    }

    /**
     * @return array<string, AttributeTypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }
}
