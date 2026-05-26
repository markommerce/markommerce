<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('catalog_products')]
class Product extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 64, unique: true)]
    public string $sku = '';

    #[Column(length: 255)]
    public string $name = '';

    #[Column(type: 'text', nullable: true)]
    public ?string $description = null;
}
