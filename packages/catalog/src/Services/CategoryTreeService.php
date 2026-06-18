<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Services;

use InvalidArgumentException;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Enum\NodeRemovalStrategy;
use Markommerce\Catalog\Exceptions\CannotDeleteDefaultTreeException;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Exceptions\CategoryTreeNodeNotFoundException;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Exceptions\DuplicateDefaultTreeException;
use Markommerce\Catalog\Exceptions\NodeNotInTreeException;

/**
 * Service responsible for category tree lifecycle management.
 *
 * Default-tree invariant (exactly one default tree at any time) is enforced at
 * the service layer because Marko's #[Index] attribute does not support partial
 * unique indexes natively. All methods that create or promote a default tree
 * check for an existing default before persisting.
 */
class CategoryTreeService implements CategoryTreeServiceInterface
{
    private const int POSITION_GAP = 10;

    /** @var array<int, mixed> */
    private array $materializedTreeCache = [];

    public function __construct(
        private CategoryTreeRepositoryInterface $categoryTreeRepository,
        private CategoryTreeNodeRepositoryInterface $categoryTreeNodeRepository,
        private CategoryRepositoryInterface $categoryRepository,
    ) {}

    /**
     * @throws InvalidArgumentException|DuplicateDefaultTreeException
     */
    public function createTree(
        string $code,
        string $name,
        bool $isDefault = false,
    ): CategoryTree {
        if (trim($code) === '') {
            throw new InvalidArgumentException('Category tree code must not be empty');
        }

        if ($isDefault) {
            $this->guardNoDuplicateDefault($code);
        }

        $tree = new CategoryTree();
        $tree->code = $code;
        $tree->name = $name;
        $tree->isDefault = $isDefault;

        $this->categoryTreeRepository->save($tree);

        return $tree;
    }

    /**
     * @throws CategoryTreeNotFoundException
     */
    public function setDefaultTree(int $treeId): void
    {
        $target = $this->categoryTreeRepository->find($treeId);

        if ($target === null) {
            throw CategoryTreeNotFoundException::forId($treeId);
        }

        // Demote the current default if one exists; having no current default
        // is valid during an initial setup or after an unusual state.
        try {
            $current = $this->categoryTreeRepository->findDefault();
            $current->isDefault = false;
            $this->categoryTreeRepository->save($current);
        } catch (DefaultTreeMissingException) {
            // No current default — proceed without demotion.
        }

        $target->isDefault = true;
        $this->categoryTreeRepository->save($target);
    }

    /**
     * @throws CategoryTreeNotFoundException|CannotDeleteDefaultTreeException
     */
    public function deleteTree(int $treeId): void
    {
        $tree = $this->categoryTreeRepository->find($treeId);

        if ($tree === null) {
            throw CategoryTreeNotFoundException::forId($treeId);
        }

        if ($tree->isDefault) {
            throw CannotDeleteDefaultTreeException::forTreeId($treeId);
        }

        $this->categoryTreeRepository->delete($tree);
    }

    /**
     * Idempotent: returns the existing default tree if one is configured,
     * otherwise creates a new tree with code='default', name='Default',
     * and isDefault=true.
     */
    public function ensureDefaultTreeExists(): CategoryTree
    {
        try {
            return $this->categoryTreeRepository->findDefault();
        } catch (DefaultTreeMissingException) {
            return $this->createTree(code: 'default', name: 'Default', isDefault: true);
        }
    }

    /**
     * @throws CategoryTreeNotFoundException|CategoryNotFoundException|CategoryTreeNodeNotFoundException|NodeNotInTreeException
     */
    public function placeCategory(
        int $treeId,
        int $categoryId,
        ?int $parentNodeId = null,
        ?int $position = null,
    ): CategoryTreeNode {
        $tree = $this->categoryTreeRepository->find($treeId);

        if ($tree === null) {
            throw CategoryTreeNotFoundException::forId($treeId);
        }

        $category = $this->categoryRepository->find($categoryId);

        if ($category === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        if ($parentNodeId !== null) {
            $parentNode = $this->categoryTreeNodeRepository->find($parentNodeId);

            if ($parentNode === null) {
                throw CategoryTreeNodeNotFoundException::forId($parentNodeId);
            }

            if ($parentNode->treeId !== $treeId) {
                throw NodeNotInTreeException::forNodeAndTree($parentNodeId, $treeId, (int) $parentNode->treeId);
            }
        }

        if ($position === null) {
            $siblings = $this->categoryTreeNodeRepository->findChildren($parentNodeId, $treeId);

            if (count($siblings) === 0) {
                $position = 0;
            } else {
                $maxPosition = max(array_map(fn (CategoryTreeNode $n) => $n->position, $siblings));
                $position = $maxPosition + self::POSITION_GAP;
            }
        }

        $node = new CategoryTreeNode();
        $node->treeId = $treeId;
        $node->categoryId = $categoryId;
        $node->parentNodeId = $parentNodeId;
        $node->position = $position;

        $this->categoryTreeNodeRepository->save($node);

        unset($this->materializedTreeCache[$treeId]);

        return $node;
    }

    /**
     * @throws CategoryTreeNodeNotFoundException|NodeNotInTreeException|CircularNodeReferenceException
     */
    public function moveNode(
        int $nodeId,
        ?int $newParentNodeId,
        int $position,
    ): void {
        $node = $this->categoryTreeNodeRepository->find($nodeId);

        if ($node === null) {
            throw CategoryTreeNodeNotFoundException::forId($nodeId);
        }

        if ($newParentNodeId === $nodeId) {
            throw CircularNodeReferenceException::forNodeAndParent($nodeId, $newParentNodeId);
        }

        if ($newParentNodeId !== null) {
            $newParent = $this->categoryTreeNodeRepository->find($newParentNodeId);

            if ($newParent === null) {
                throw CategoryTreeNodeNotFoundException::forId($newParentNodeId);
            }

            if ($newParent->treeId !== $node->treeId) {
                throw NodeNotInTreeException::forNodeAndTree(
                    $newParentNodeId,
                    (int) $node->treeId,
                    (int) $newParent->treeId,
                );
            }

            $this->guardNoCycle($nodeId, $newParentNodeId);
        }

        $node->parentNodeId = $newParentNodeId;
        $node->position = $position;

        $this->categoryTreeNodeRepository->save($node);

        unset($this->materializedTreeCache[(int) $node->treeId]);
    }

    /**
     * @throws CategoryTreeNodeNotFoundException
     */
    public function removeNode(
        int $nodeId,
        NodeRemovalStrategy $strategy,
    ): void {
        $node = $this->categoryTreeNodeRepository->find($nodeId);

        if ($node === null) {
            throw CategoryTreeNodeNotFoundException::forId($nodeId);
        }

        $treeId = (int) $node->treeId;

        if ($strategy === NodeRemovalStrategy::CASCADE) {
            $this->cascadeDelete($nodeId, $treeId);
        } else {
            $children = $this->categoryTreeNodeRepository->findChildren($nodeId, $treeId);

            foreach ($children as $child) {
                $child->parentNodeId = $node->parentNodeId;
                $this->categoryTreeNodeRepository->save($child);
            }

            $this->categoryTreeNodeRepository->delete($node);
        }

        unset($this->materializedTreeCache[$treeId]);
    }

    /**
     * @param list<int> $orderedNodeIds
     * @throws CategoryTreeNodeNotFoundException|NodeNotInTreeException
     */
    public function reorderSiblings(
        ?int $parentNodeId,
        int $treeId,
        array $orderedNodeIds,
    ): void {
        foreach ($orderedNodeIds as $id) {
            $node = $this->categoryTreeNodeRepository->find($id);

            if ($node === null) {
                throw CategoryTreeNodeNotFoundException::forId($id);
            }

            if ($node->treeId !== $treeId) {
                throw NodeNotInTreeException::forNodeAndTree($id, $treeId, (int) $node->treeId);
            }

            if ($node->parentNodeId !== $parentNodeId) {
                throw NodeNotInTreeException::forParentMismatch($id, $parentNodeId, $node->parentNodeId);
            }
        }

        foreach ($orderedNodeIds as $index => $id) {
            $node = $this->categoryTreeNodeRepository->find($id);

            if ($node !== null) {
                $node->position = $index * self::POSITION_GAP;
                $this->categoryTreeNodeRepository->save($node);
            }
        }

        unset($this->materializedTreeCache[$treeId]);
    }

    /**
     * @return array<int, array{node: CategoryTreeNode, category_id: int, children: array<int, mixed>}>
     * @throws CategoryTreeNotFoundException
     */
    public function getMaterializedTree(int $treeId): array
    {
        if (isset($this->materializedTreeCache[$treeId])) {
            return $this->materializedTreeCache[$treeId];
        }

        $tree = $this->categoryTreeRepository->find($treeId);

        if ($tree === null) {
            throw CategoryTreeNotFoundException::forId($treeId);
        }

        $nodes = $this->categoryTreeNodeRepository->findByTree($treeId);

        /** @var array<int|string, list<CategoryTreeNode>> $byParent */
        $byParent = [];

        foreach ($nodes as $node) {
            $key = $node->parentNodeId ?? 'root';
            $byParent[$key][] = $node;
        }

        foreach ($byParent as &$siblings) {
            usort($siblings, fn (CategoryTreeNode $a, CategoryTreeNode $b) => $a->position <=> $b->position);
        }

        unset($siblings);

        $build = function (?int $parentId) use (&$build, $byParent): array {
            $key = $parentId ?? 'root';
            $children = $byParent[$key] ?? [];

            return array_map(fn (CategoryTreeNode $node) => [
                'node' => $node,
                'category_id' => (int) $node->categoryId,
                'children' => $build($node->id),
            ], $children);
        };

        $result = $build(null);

        $this->materializedTreeCache[$treeId] = $result;

        return $result;
    }

    /**
     * @throws DuplicateDefaultTreeException
     */
    private function guardNoDuplicateDefault(string $code): void
    {
        try {
            $this->categoryTreeRepository->findDefault();
            throw DuplicateDefaultTreeException::forCode($code);
        } catch (DefaultTreeMissingException) {
            // No existing default — safe to proceed.
        }
    }

    private function cascadeDelete(
        int $nodeId,
        int $treeId,
    ): void {
        $children = $this->categoryTreeNodeRepository->findChildren($nodeId, $treeId);

        foreach ($children as $child) {
            $this->cascadeDelete((int) $child->id, $treeId);
        }

        $node = $this->categoryTreeNodeRepository->find($nodeId);

        if ($node !== null) {
            $this->categoryTreeNodeRepository->delete($node);
        }
    }

    /**
     * @throws CircularNodeReferenceException
     */
    private function guardNoCycle(
        int $nodeId,
        int $newParentNodeId,
    ): void {
        $current = $this->categoryTreeNodeRepository->find($newParentNodeId);

        while ($current !== null) {
            if ($current->parentNodeId === null) {
                break;
            }

            if ($current->parentNodeId === $nodeId) {
                throw CircularNodeReferenceException::forNodeAndParent($nodeId, $newParentNodeId);
            }

            $current = $this->categoryTreeNodeRepository->find($current->parentNodeId);
        }
    }
}
