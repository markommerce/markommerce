<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('attribute_options')]
class AttributeOption extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'attribute_id', references: 'attribute_definitions', onDelete: 'CASCADE')]
    public ?int $attributeId = null;

    #[Column(length: 255)]
    public string $value = '';

    #[Column(length: 255)]
    public string $label = '';

    #[Column(default: 0)]
    public int $position = 0;
}
