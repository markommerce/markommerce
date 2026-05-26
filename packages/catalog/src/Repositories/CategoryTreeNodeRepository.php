<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repositories;

use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTreeNode;

/**
 * @extends Repository<CategoryTreeNode>
 */
class CategoryTreeNodeRepository extends Repository implements CategoryTreeNodeRepositoryInterface
{
    protected const string ENTITY_CLASS = CategoryTreeNode::class;

    /**
     * Find all nodes belonging to a tree.
     *
     * @return list<CategoryTreeNode>
     * @throws RepositoryException
     */
    public function findByTree(int $treeId): array
    {
        /** @var list<CategoryTreeNode> */
        return $this->findBy(['treeId' => $treeId])->toArray();
    }

    /**
     * Find direct children of the given parent node, sorted by position.
     * Pass null for $parentNodeId to retrieve root nodes.
     *
     * @return list<CategoryTreeNode>
     */
    public function findChildren(?int $parentNodeId, int $treeId): array
    {
        if ($parentNodeId === null) {
            $sql = 'SELECT * FROM catalog_category_tree_nodes WHERE tree_id = ? AND parent_node_id IS NULL ORDER BY position ASC';
            $bindings = [$treeId];
        } else {
            $sql = 'SELECT * FROM catalog_category_tree_nodes WHERE tree_id = ? AND parent_node_id = ? ORDER BY position ASC';
            $bindings = [$treeId, $parentNodeId];
        }

        $rows = $this->connection->query($sql, $bindings);

        /** @var list<CategoryTreeNode> */
        return array_values(array_map(
            fn (array $row): CategoryTreeNode => $this->hydrator->hydrate(
                CategoryTreeNode::class,
                $row,
                $this->metadata,
            ),
            $rows,
        ));
    }

    /**
     * Convenience shortcut for findChildren(null, $treeId).
     *
     * @return list<CategoryTreeNode>
     */
    public function findRoots(int $treeId): array
    {
        return $this->findChildren(null, $treeId);
    }

    /**
     * Find all placements of a category within a specific tree (multi-placement support).
     *
     * @return list<CategoryTreeNode>
     * @throws RepositoryException
     */
    public function findByCategoryInTree(int $categoryId, int $treeId): array
    {
        /** @var list<CategoryTreeNode> */
        return $this->findBy(['categoryId' => $categoryId, 'treeId' => $treeId])->toArray();
    }

    /**
     * Find all placements of a category across all trees.
     * Used by the category deletion guard.
     *
     * @return list<CategoryTreeNode>
     * @throws RepositoryException
     */
    public function findByCategoryAcrossTrees(int $categoryId): array
    {
        /** @var list<CategoryTreeNode> */
        return $this->findBy(['categoryId' => $categoryId])->toArray();
    }
}
