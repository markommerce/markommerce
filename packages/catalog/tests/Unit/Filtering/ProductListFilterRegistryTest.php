<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Filtering\ProductListFilterInterface;
use Markommerce\Catalog\Filtering\ProductListFilterRegistry;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class StubProductListFilter implements ProductListFilterInterface
{
    public function apply(
        RepositoryQueryBuilder $repositoryQueryBuilder,
        FilterSelection $filterSelection,
    ): void {}
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers and lists product-list filter contributors', function (): void {
    $registry = new ProductListFilterRegistry();
    $filter   = new StubProductListFilter();
    $registry->register($filter);

    expect($registry->all())->toBe([$filter]);
});
