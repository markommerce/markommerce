<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Tests\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('fixture_simple')]
class SimpleEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'product_sku', length: 64)]
    public string $productSku = '';

    #[Column(length: 255)]
    public string $name = '';
}
