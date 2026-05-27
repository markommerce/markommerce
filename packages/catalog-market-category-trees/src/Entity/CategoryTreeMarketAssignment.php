<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarketCategoryTrees\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('catalog_category_tree_market_assignments')]
class CategoryTreeMarketAssignment extends Entity
{
    #[Column(primaryKey: true, length: 64)]
    public string $market = '';

    #[Column(name: 'tree_id', references: 'catalog_category_trees', onDelete: 'RESTRICT')]
    public ?int $treeId = null;
}
