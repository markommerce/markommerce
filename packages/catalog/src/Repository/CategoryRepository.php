<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repository;

use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Entity\Category;

/**
 * @extends Repository<Category>
 * @implements CategoryRepositoryInterface<Category>
 */
class CategoryRepository extends Repository implements CategoryRepositoryInterface
{
    protected const string ENTITY_CLASS = Category::class;
}
