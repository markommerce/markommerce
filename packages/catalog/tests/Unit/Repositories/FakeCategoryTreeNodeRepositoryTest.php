<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;

it('interface extends Marko\Database\Repository\RepositoryInterface', function (): void {
    $reflection = new ReflectionClass(CategoryTreeNodeRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
});

it(
    'interface declares the five custom methods (findByTree, findChildren, findRoots, findByCategoryInTree, findByCategoryAcrossTrees)',
    function (): void {
        $reflection = new ReflectionClass(CategoryTreeNodeRepositoryInterface::class);

        $ownMethods = array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            array_filter(
                $reflection->getMethods(),
                fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === CategoryTreeNodeRepositoryInterface::class,
            ),
        );

        expect($ownMethods)->toContain('findByTree');
        expect($ownMethods)->toContain('findChildren');
        expect($ownMethods)->toContain('findRoots');
        expect($ownMethods)->toContain('findByCategoryInTree');
        expect($ownMethods)->toContain('findByCategoryAcrossTrees');
        expect(count($ownMethods))->toBe(5);

        $findByTree = $reflection->getMethod('findByTree');
        $params = $findByTree->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('treeId');
        expect((string) $params[0]->getType())->toBe('int');

        $findChildren = $reflection->getMethod('findChildren');
        $params = $findChildren->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('parentNodeId');
        expect($params[1]->getName())->toBe('treeId');

        $findRoots = $reflection->getMethod('findRoots');
        $params = $findRoots->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('treeId');
        expect((string) $params[0]->getType())->toBe('int');

        $findByCategoryInTree = $reflection->getMethod('findByCategoryInTree');
        $params = $findByCategoryInTree->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('categoryId');
        expect($params[1]->getName())->toBe('treeId');

        $findByCategoryAcrossTrees = $reflection->getMethod('findByCategoryAcrossTrees');
        $params = $findByCategoryAcrossTrees->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('categoryId');
        expect((string) $params[0]->getType())->toBe('int');
    },
);

it('fake stores a node on save and returns it from find by id', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();
    $node = new CategoryTreeNode();
    $node->treeId = 1;
    $node->categoryId = 10;
    $node->parentNodeId = null;
    $node->position = 0;

    $repository->save($node);

    expect($node->id)->not->toBeNull();
    assert($node->id !== null);

    $found = $repository->find($node->id);

    expect($found)->not->toBeNull();
    assert($found !== null);

    expect($found->id)->toBe($node->id);
    expect($found->categoryId)->toBe(10);
});

it('fake assigns an id when saving a node with null id', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();
    $node = new CategoryTreeNode();
    $node->treeId = 1;
    $node->categoryId = 10;

    expect($node->id)->toBeNull();

    $repository->save($node);

    expect($node->id)->not->toBeNull();
    expect($node->id)->toBeInt();
});

it('fake findByTree returns all nodes belonging to the tree', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();

    $nodeInTree1a = new CategoryTreeNode();
    $nodeInTree1a->treeId = 1;
    $nodeInTree1a->categoryId = 10;

    $nodeInTree1b = new CategoryTreeNode();
    $nodeInTree1b->treeId = 1;
    $nodeInTree1b->categoryId = 20;

    $nodeInTree2 = new CategoryTreeNode();
    $nodeInTree2->treeId = 2;
    $nodeInTree2->categoryId = 30;

    $repository->save($nodeInTree1a);
    $repository->save($nodeInTree1b);
    $repository->save($nodeInTree2);

    $result = $repository->findByTree(1);

    expect($result)->toHaveCount(2);
    expect(array_any($result, fn (CategoryTreeNode $n) => $n->categoryId === 10))->toBeTrue();
    expect(array_any($result, fn (CategoryTreeNode $n) => $n->categoryId === 20))->toBeTrue();
    expect(array_any($result, fn (CategoryTreeNode $n) => $n->categoryId === 30))->toBeFalse();
});

it('fake findChildren returns direct children of the given parent in position order', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();

    $parent = new CategoryTreeNode();
    $parent->treeId = 1;
    $parent->categoryId = 1;
    $parent->parentNodeId = null;
    $parent->position = 0;
    $repository->save($parent);

    assert($parent->id !== null);

    $childB = new CategoryTreeNode();
    $childB->treeId = 1;
    $childB->categoryId = 20;
    $childB->parentNodeId = $parent->id;
    $childB->position = 2;

    $childA = new CategoryTreeNode();
    $childA->treeId = 1;
    $childA->categoryId = 10;
    $childA->parentNodeId = $parent->id;
    $childA->position = 1;

    $childC = new CategoryTreeNode();
    $childC->treeId = 1;
    $childC->categoryId = 30;
    $childC->parentNodeId = $parent->id;
    $childC->position = 3;

    $repository->save($childB);
    $repository->save($childA);
    $repository->save($childC);

    $children = $repository->findChildren($parent->id, 1);

    expect($children)->toHaveCount(3);
    expect($children[0]->categoryId)->toBe(10);
    expect($children[1]->categoryId)->toBe(20);
    expect($children[2]->categoryId)->toBe(30);
});

it('fake findChildren with null parent returns root nodes', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();

    $rootB = new CategoryTreeNode();
    $rootB->treeId = 1;
    $rootB->categoryId = 20;
    $rootB->parentNodeId = null;
    $rootB->position = 2;

    $rootA = new CategoryTreeNode();
    $rootA->treeId = 1;
    $rootA->categoryId = 10;
    $rootA->parentNodeId = null;
    $rootA->position = 1;

    $child = new CategoryTreeNode();
    $child->treeId = 1;
    $child->categoryId = 30;
    $child->parentNodeId = 99;
    $child->position = 1;

    $repository->save($rootB);
    $repository->save($rootA);
    $repository->save($child);

    $roots = $repository->findChildren(null, 1);

    expect($roots)->toHaveCount(2);
    expect($roots[0]->categoryId)->toBe(10);
    expect($roots[1]->categoryId)->toBe(20);
});

it('fake findRoots is equivalent to findChildren with null parent', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();

    $rootB = new CategoryTreeNode();
    $rootB->treeId = 1;
    $rootB->categoryId = 20;
    $rootB->parentNodeId = null;
    $rootB->position = 2;

    $rootA = new CategoryTreeNode();
    $rootA->treeId = 1;
    $rootA->categoryId = 10;
    $rootA->parentNodeId = null;
    $rootA->position = 1;

    $repository->save($rootB);
    $repository->save($rootA);

    $fromFindChildren = $repository->findChildren(null, 1);
    $fromFindRoots = $repository->findRoots(1);

    expect($fromFindRoots)->toHaveCount(count($fromFindChildren));
    expect($fromFindRoots[0]->categoryId)->toBe($fromFindChildren[0]->categoryId);
    expect($fromFindRoots[1]->categoryId)->toBe($fromFindChildren[1]->categoryId);
});

it(
    'fake findByCategoryInTree returns all placements of a category within a tree (multi-placement)',
    function (): void {
        $repository = new FakeCategoryTreeNodeRepository();

        $placement1 = new CategoryTreeNode();
        $placement1->treeId = 1;
        $placement1->categoryId = 42;
        $placement1->parentNodeId = null;
        $placement1->position = 1;

        $placement2 = new CategoryTreeNode();
        $placement2->treeId = 1;
        $placement2->categoryId = 42;
        $placement2->parentNodeId = 99;
        $placement2->position = 1;

        $otherTree = new CategoryTreeNode();
        $otherTree->treeId = 2;
        $otherTree->categoryId = 42;
        $otherTree->parentNodeId = null;
        $otherTree->position = 1;

        $otherCategory = new CategoryTreeNode();
        $otherCategory->treeId = 1;
        $otherCategory->categoryId = 99;
        $otherCategory->parentNodeId = null;
        $otherCategory->position = 2;

        $repository->save($placement1);
        $repository->save($placement2);
        $repository->save($otherTree);
        $repository->save($otherCategory);

        $result = $repository->findByCategoryInTree(42, 1);

        expect($result)->toHaveCount(2);
        expect(array_all($result, fn (CategoryTreeNode $n) => $n->categoryId === 42))->toBeTrue();
        expect(array_all($result, fn (CategoryTreeNode $n) => $n->treeId === 1))->toBeTrue();
    },
);

it('fake findByCategoryAcrossTrees returns all placements regardless of tree', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();

    $inTree1 = new CategoryTreeNode();
    $inTree1->treeId = 1;
    $inTree1->categoryId = 42;
    $inTree1->parentNodeId = null;
    $inTree1->position = 1;

    $inTree2 = new CategoryTreeNode();
    $inTree2->treeId = 2;
    $inTree2->categoryId = 42;
    $inTree2->parentNodeId = null;
    $inTree2->position = 1;

    $inTree3 = new CategoryTreeNode();
    $inTree3->treeId = 3;
    $inTree3->categoryId = 42;
    $inTree3->parentNodeId = null;
    $inTree3->position = 1;

    $differentCategory = new CategoryTreeNode();
    $differentCategory->treeId = 1;
    $differentCategory->categoryId = 99;
    $differentCategory->parentNodeId = null;
    $differentCategory->position = 2;

    $repository->save($inTree1);
    $repository->save($inTree2);
    $repository->save($inTree3);
    $repository->save($differentCategory);

    $result = $repository->findByCategoryAcrossTrees(42);

    expect($result)->toHaveCount(3);
    expect(array_all($result, fn (CategoryTreeNode $n) => $n->categoryId === 42))->toBeTrue();
    expect(array_any($result, fn (CategoryTreeNode $n) => $n->treeId === 1))->toBeTrue();
    expect(array_any($result, fn (CategoryTreeNode $n) => $n->treeId === 2))->toBeTrue();
    expect(array_any($result, fn (CategoryTreeNode $n) => $n->treeId === 3))->toBeTrue();
});

it('fake removes a node on delete', function (): void {
    $repository = new FakeCategoryTreeNodeRepository();
    $node = new CategoryTreeNode();
    $node->treeId = 1;
    $node->categoryId = 10;

    $repository->save($node);

    assert($node->id !== null);
    expect($repository->find($node->id))->not->toBeNull();

    $repository->delete($node);

    assert($node->id !== null);
    expect($repository->find($node->id))->toBeNull();
});
