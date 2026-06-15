<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Contracts;

use Markommerce\Attribute\Type\AttributeBacking;

interface AttributeDefinitionInterface
{
    public function code(): string;

    public function entityType(): string;

    public function type(): string;

    public function backing(): AttributeBacking;

    public function isRequired(): bool;

    /**
     * @return array<string, mixed>
     */
    public function config(): array;
}
