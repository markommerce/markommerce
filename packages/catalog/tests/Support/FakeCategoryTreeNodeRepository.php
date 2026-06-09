<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTreeNode;

class FakeCategoryTreeNodeRepository implements CategoryTreeNodeRepositoryInterface
{
    /** @var array<int, CategoryTreeNode> */
    public array $nodes = [];

    /** @var list<string> */
    public array $callLog = [];

    private int $nextId = 1;

    public function find(int|string $id): ?CategoryTreeNode
    {
        return array_find($this->nodes, fn (CategoryTreeNode $n) => $n->id === $id);
    }

    /**
     * @throws RepositoryException
     */
    public function findOrFail(int|string $id): CategoryTreeNode
    {
        $node = $this->find($id);

        if ($node === null) {
            throw RepositoryException::entityNotFound(CategoryTreeNode::class, $id);
        }

        return $node;
    }

    /**
     * @return EntityCollection<CategoryTreeNode>
     */
    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->nodes));
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<CategoryTreeNode>
     */
    public function findBy(array $criteria): EntityCollection
    {
        $matches = array_values(array_filter(
            $this->nodes,
            fn (CategoryTreeNode $n) => $this->matchesCriteria($n, $criteria),
        ));

        return new EntityCollection($matches);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?CategoryTreeNode
    {
        return array_find(
            $this->nodes,
            fn (CategoryTreeNode $n) => $this->matchesCriteria($n, $criteria),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return array_any(
            $this->nodes,
            fn (CategoryTreeNode $n) => $this->matchesCriteria($n, $criteria),
        );
    }

    /**
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        if (!$entity instanceof CategoryTreeNode) {
            throw RepositoryException::invalidEntityType(self::class, CategoryTreeNode::class, $entity::class);
        }

        if ($entity->id === null) {
            $entity->id = $this->nextId++;
        }

        $this->nodes[$entity->id] = $entity;
    }

    /**
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if (!$entity instanceof CategoryTreeNode) {
            throw RepositoryException::invalidEntityType(self::class, CategoryTreeNode::class, $entity::class);
        }

        if ($entity->id !== null) {
            unset($this->nodes[$entity->id]);
        }
    }

    /**
     * @param array<Entity> $entities
     * @throws RepositoryException
     */
    public function insertBatch(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->save($entity);
        }
    }

    /**
     * @return list<CategoryTreeNode>
     */
    public function findByTree(int $treeId): array
    {
        $this->callLog[] = 'findByTree';

        return array_values(array_filter(
            $this->nodes,
            fn (CategoryTreeNode $n) => $n->treeId === $treeId,
        ));
    }

    /**
     * @return list<CategoryTreeNode>
     */
    public function findChildren(
        ?int $parentNodeId,
        int $treeId,
    ): array
    {
        $children = array_values(array_filter(
            $this->nodes,
            fn (CategoryTreeNode $n) => $n->treeId === $treeId && $n->parentNodeId === $parentNodeId,
        ));

        usort($children, fn (CategoryTreeNode $a, CategoryTreeNode $b) => $a->position <=> $b->position);

        return $children;
    }

    /**
     * @return list<CategoryTreeNode>
     */
    public function findRoots(int $treeId): array
    {
        return $this->findChildren(null, $treeId);
    }

    /**
     * @return list<CategoryTreeNode>
     */
    public function findByCategoryInTree(
        int $categoryId,
        int $treeId,
    ): array
    {
        return array_values(array_filter(
            $this->nodes,
            fn (CategoryTreeNode $n) => $n->categoryId === $categoryId && $n->treeId === $treeId,
        ));
    }

    /**
     * @return list<CategoryTreeNode>
     */
    public function findByCategoryAcrossTrees(int $categoryId): array
    {
        return array_values(array_filter(
            $this->nodes,
            fn (CategoryTreeNode $n) => $n->categoryId === $categoryId,
        ));
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(
        CategoryTreeNode $node,
        array $criteria,
    ): bool
    {
        return array_all(
            array_keys($criteria),
            fn (string $key) => $node->$key === $criteria[$key],
        );
    }
}
