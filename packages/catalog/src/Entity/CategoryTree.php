<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('catalog_category_trees')]
class CategoryTree extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 64, unique: true)]
    public string $code = '';

    #[Column(length: 255)]
    public string $name = '';

    #[Column(name: 'is_default')]
    public bool $isDefault = false;
}
