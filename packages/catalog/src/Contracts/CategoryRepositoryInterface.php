<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\Category;

/**
 * @extends RepositoryInterface<Category>
 */
interface CategoryRepositoryInterface extends RepositoryInterface {}
