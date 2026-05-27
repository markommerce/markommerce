<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarket\Plugins;

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Exceptions\TreeHasMarketAssignmentsException;

#[Plugin(target: CategoryTreeServiceInterface::class)]
readonly class CategoryTreeServiceDeletePlugin
{
    public function __construct(
        private CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository,
    ) {}

    /**
     * @throws TreeHasMarketAssignmentsException
     */
    #[Before(method: 'deleteTree')]
    public function beforeDeleteTree(int $treeId): void
    {
        $assignments = $this->categoryTreeMarketAssignmentRepository->findByTree($treeId);

        if (count($assignments) > 0) {
            $markets = array_map(fn ($a) => $a->market, $assignments);
            throw TreeHasMarketAssignmentsException::forTreeId($treeId, $markets);
        }
    }
}
