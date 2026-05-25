<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTreeNode;

/**
 * @extends RepositoryInterface<CategoryTreeNode>
 */
interface CategoryTreeNodeRepositoryInterface extends RepositoryInterface
{
    /**
     * Find all nodes belonging to a tree.
     *
     * @return list<CategoryTreeNode>
     */
    public function findByTree(int $treeId): array;

    /**
     * Find direct children of the given parent node, sorted by position.
     * Pass null for $parentNodeId to retrieve root nodes.
     *
     * @return list<CategoryTreeNode>
     */
    public function findChildren(?int $parentNodeId, int $treeId): array;

    /**
     * Convenience shortcut for findChildren(null, $treeId).
     *
     * @return list<CategoryTreeNode>
     */
    public function findRoots(int $treeId): array;

    /**
     * Find all placements of a category within a specific tree (multi-placement support).
     *
     * @return list<CategoryTreeNode>
     */
    public function findByCategoryInTree(int $categoryId, int $treeId): array;

    /**
     * Find all placements of a category across all trees.
     * Used by the category deletion guard.
     *
     * @return list<CategoryTreeNode>
     */
    public function findByCategoryAcrossTrees(int $categoryId): array;
}
