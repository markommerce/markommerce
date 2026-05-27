<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarketCategoryTrees\Services;

use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\CatalogMarketCategoryTrees\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;

class CategoryTreeMarketResolver
{
    public function __construct(
        private CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository,
        private CategoryTreeRepositoryInterface $categoryTreeRepository,
    ) {}

    /**
     * @throws CategoryTreeNotFoundException|DefaultTreeMissingException
     */
    public function resolveTreeForMarket(string $market): CategoryTree
    {
        $assignment = $this->categoryTreeMarketAssignmentRepository->findByMarket($market);

        if ($assignment !== null) {
            $treeId = (int) $assignment->treeId;
            $tree = $this->categoryTreeRepository->find($treeId);

            if ($tree === null) {
                throw CategoryTreeNotFoundException::forId($treeId);
            }

            return $tree;
        }

        return $this->categoryTreeRepository->findDefault();
    }
}
