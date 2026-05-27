<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarketCategoryTrees\Services;

use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\CatalogMarketCategoryTrees\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarketCategoryTrees\Entity\CategoryTreeMarketAssignment;

class CategoryTreeMarketAssignmentService
{
    public function __construct(
        private CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository,
        private CategoryTreeRepositoryInterface $categoryTreeRepository,
    ) {}

    /**
     * @throws CategoryTreeNotFoundException
     */
    public function assignTreeToMarket(int $treeId, string $market): void
    {
        $tree = $this->categoryTreeRepository->find($treeId);

        if ($tree === null) {
            throw CategoryTreeNotFoundException::forId($treeId);
        }

        $assignment = new CategoryTreeMarketAssignment();
        $assignment->market = $market;
        $assignment->treeId = $treeId;

        $this->categoryTreeMarketAssignmentRepository->save($assignment);
    }

    public function unassignMarket(string $market): void
    {
        $assignment = $this->categoryTreeMarketAssignmentRepository->findByMarket($market);

        if ($assignment === null) {
            return;
        }

        $this->categoryTreeMarketAssignmentRepository->delete($assignment);
    }
}
