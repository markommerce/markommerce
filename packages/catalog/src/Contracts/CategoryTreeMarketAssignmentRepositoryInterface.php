<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTreeMarketAssignment;

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
