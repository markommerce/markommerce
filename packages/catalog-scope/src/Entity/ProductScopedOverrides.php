<?php

declare(strict_types=1);

namespace Markommerce\CatalogScope\Entity;

use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table(extends: Product::class)]
class ProductScopedOverrides extends Entity implements HasScopesInterface
{
    use HasScopes;
}
