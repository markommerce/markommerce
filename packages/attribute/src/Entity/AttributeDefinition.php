<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Type\AttributeBacking;

#[Table('attribute_definitions')]
class AttributeDefinition extends Entity implements AttributeDefinitionInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 64)]
    public string $code = '';

    #[Column(name: 'entity_type', length: 64)]
    public string $entityType = '';

    #[Column(length: 64)]
    public string $type = '';

    #[Column(length: 255)]
    public string $label = '';

    #[Column]
    public bool $required = false;

    #[Column(name: 'default_value', type: 'text', nullable: true)]
    public ?string $defaultValue = null;

    #[Column(length: 16)]
    public string $backing = 'Json';

    #[Column]
    public bool $filterable = false;

    #[Column]
    public bool $searchable = false;

    #[Column]
    public bool $facetable = false;

    #[Column]
    public bool $scopable = false;

    /** @var array<string, mixed>|null */
    #[Column(type: 'json', nullable: true)]
    public ?array $config = null;

    public function code(): string
    {
        return $this->code;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function backing(): AttributeBacking
    {
        return match ($this->backing) {
            'Column' => AttributeBacking::Column,
            default => AttributeBacking::Json,
        };
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config ?? [];
    }
}
