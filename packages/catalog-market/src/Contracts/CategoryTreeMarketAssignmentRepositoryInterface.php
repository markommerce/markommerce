<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarket\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment;

/**
 * @extends RepositoryInterface<CategoryTreeMarketAssignment>
 */
interface CategoryTreeMarketAssignmentRepositoryInterface extends RepositoryInterface
{
    public function findByMarket(string $market): ?CategoryTreeMarketAssignment;

    /**
     * @return list<CategoryTreeMarketAssignment>
     */
    public function findByTree(int $treeId): array;
}
