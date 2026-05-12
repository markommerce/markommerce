<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('categories')]
class Category extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /**
     * TODO multi-store: scope this per store-view when the stores/config module lands.
     */
    #[Column(length: 255)]
    public string $name;
}
