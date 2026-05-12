<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repository;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\Category;

/**
 * Interface for Category entity repository.
 *
 * @template TEntity of Category
 * @extends RepositoryInterface<TEntity>
 */
interface CategoryRepositoryInterface extends RepositoryInterface {}
