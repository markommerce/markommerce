<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Validation;

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Type\AttributeBacking;

readonly class DefinitionWithOptions implements AttributeDefinitionInterface
{
    /**
     * @param list<string> $allowedOptions
     */
    public function __construct(
        private AttributeDefinitionInterface $definition,
        private array $allowedOptions,
    ) {}

    public function code(): string
    {
        return $this->definition->code();
    }

    public function entityType(): string
    {
        return $this->definition->entityType();
    }

    public function type(): string
    {
        return $this->definition->type();
    }

    public function backing(): AttributeBacking
    {
        return $this->definition->backing();
    }

    public function isRequired(): bool
    {
        return $this->definition->isRequired();
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return array_merge($this->definition->config(), ['options' => $this->allowedOptions]);
    }
}
