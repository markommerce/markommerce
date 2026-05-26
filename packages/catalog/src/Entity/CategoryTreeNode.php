<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('catalog_category_tree_nodes')]
#[Index(name: 'uniq_node_position', columns: ['tree_id', 'parent_node_id', 'position'], unique: true)]
#[Index(name: 'idx_node_tree_category', columns: ['tree_id', 'category_id'])]
#[Index(name: 'idx_node_parent_position', columns: ['parent_node_id', 'position'])]
class CategoryTreeNode extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'tree_id', references: 'catalog_category_trees', onDelete: 'CASCADE')]
    public ?int $treeId = null;

    #[Column(name: 'category_id', references: 'catalog_categories', onDelete: 'RESTRICT')]
    public ?int $categoryId = null;

    #[Column(name: 'parent_node_id', references: 'catalog_category_tree_nodes', onDelete: 'CASCADE', nullable: true)]
    public ?int $parentNodeId = null;

    #[Column]
    public int $position = 0;
}
