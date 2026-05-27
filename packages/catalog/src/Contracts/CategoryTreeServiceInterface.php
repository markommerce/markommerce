<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Contracts;

use InvalidArgumentException;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Enum\NodeRemovalStrategy;
use Markommerce\Catalog\Exceptions\CannotDeleteDefaultTreeException;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Exceptions\CategoryTreeNodeNotFoundException;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;
use Markommerce\Catalog\Exceptions\DuplicateDefaultTreeException;
use Markommerce\Catalog\Exceptions\NodeNotInTreeException;

interface CategoryTreeServiceInterface
{
    /**
     * @throws InvalidArgumentException|DuplicateDefaultTreeException
     */
    public function createTree(string $code, string $name, bool $isDefault = false): CategoryTree;

    /**
     * @throws CategoryTreeNotFoundException
     */
    public function setDefaultTree(int $treeId): void;

    /**
     * @throws CategoryTreeNotFoundException|CannotDeleteDefaultTreeException
     */
    public function deleteTree(int $treeId): void;

    /**
     * Idempotent: returns the existing default tree if one is configured,
     * otherwise creates a new tree with code='default', name='Default',
     * and isDefault=true.
     */
    public function ensureDefaultTreeExists(): CategoryTree;

    /**
     * @throws CategoryTreeNotFoundException|CategoryNotFoundException|CategoryTreeNodeNotFoundException|NodeNotInTreeException
     */
    public function placeCategory(int $treeId, int $categoryId, ?int $parentNodeId = null, ?int $position = null): CategoryTreeNode;

    /**
     * @throws CategoryTreeNodeNotFoundException|NodeNotInTreeException|CircularNodeReferenceException
     */
    public function moveNode(int $nodeId, ?int $newParentNodeId, int $position): void;

    /**
     * @throws CategoryTreeNodeNotFoundException
     */
    public function removeNode(int $nodeId, NodeRemovalStrategy $strategy): void;

    /**
     * @param list<int> $orderedNodeIds
     * @throws CategoryTreeNodeNotFoundException|NodeNotInTreeException
     */
    public function reorderSiblings(?int $parentNodeId, int $treeId, array $orderedNodeIds): void;

    /**
     * @return array<int, array{node: CategoryTreeNode, category_id: int, children: array<int, mixed>}>
     * @throws CategoryTreeNotFoundException
     */
    public function getMaterializedTree(int $treeId): array;
}
