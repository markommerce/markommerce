<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;

/**
 * @extends RepositoryInterface<CategoryTree>
 */
interface CategoryTreeRepositoryInterface extends RepositoryInterface
{
    public function findByCode(string $code): ?CategoryTree;

    /**
     * @throws DefaultTreeMissingException
     */
    public function findDefault(): CategoryTree;
}
