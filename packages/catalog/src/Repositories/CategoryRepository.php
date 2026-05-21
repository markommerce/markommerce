<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repositories;

use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Entity\Category;

class CategoryRepository extends Repository implements CategoryRepositoryInterface
{
    protected const string ENTITY_CLASS = Category::class;
}
