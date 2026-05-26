<?php

declare(strict_types=1);

namespace Markommerce\CatalogScope\Entity;

use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table(extends: Category::class)]
class CategoryScopedOverrides extends Entity implements HasScopesInterface
{
    use HasScopes;
}
