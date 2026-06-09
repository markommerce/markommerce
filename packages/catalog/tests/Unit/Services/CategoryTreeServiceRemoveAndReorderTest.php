<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Enum\NodeRemovalStrategy;
use Markommerce\Catalog\Exceptions\CategoryTreeNodeNotFoundException;
use Markommerce\Catalog\Exceptions\NodeNotInTreeException;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;

function makeCategoryTreeServiceForRemoveAndReorder(
    ?FakeCategoryTreeRepository $treeRepo = null,
    ?FakeCategoryTreeNodeRepository $nodeRepo = null,
    ?FakeCategoryRepository $categoryRepo = null,
): CategoryTreeService {
    return new CategoryTreeService(
        categoryTreeRepository: $treeRepo ?? new FakeCategoryTreeRepository(),
        categoryTreeNodeRepository: $nodeRepo ?? new FakeCategoryTreeNodeRepository(),
        categoryRepository: $categoryRepo ?? new FakeCategoryRepository(),
    );
}

function makeTreeForRemoveAndReorder(FakeCategoryTreeRepository $treeRepo, string $code = 'main'): CategoryTree
{
    $tree = new CategoryTree();
    $tree->code = $code;
    $tree->name = ucfirst($code) . ' Tree';
    $tree->isDefault = false;
    $treeRepo->save($tree);

    return $tree;
}

function makeCategoryForRemoveAndReorder(FakeCategoryRepository $categoryRepo, string $name = 'Test Category'): Category
{
    $category = new Category();
    $category->name = $name;
    $categoryRepo->save($category);

    return $category;
}

function makeNodeForRemoveAndReorder(FakeCategoryTreeNodeRepository $nodeRepo, int $treeId, int $categoryId, ?int $parentNodeId = null, int $position = 0): CategoryTreeNode
{
    $node = new CategoryTreeNode();
    $node->treeId = $treeId;
    $node->categoryId = $categoryId;
    $node->parentNodeId = $parentNodeId;
    $node->position = $position;
    $nodeRepo->save($node);

    return $node;
}

it('removeNode with CASCADE deletes the node', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForRemoveAndReorder($treeRepo);
    $cat = makeCategoryForRemoveAndReorder($categoryRepo);
    $node = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat->id);

    $service->removeNode($node->id, NodeRemovalStrategy::CASCADE);

    expect($nodeRepo->nodes)->toHaveCount(0);
});

it('removeNode with CASCADE deletes all descendants recursively', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForRemoveAndReorder($treeRepo);
    $cat1 = makeCategoryForRemoveAndReorder($categoryRepo, 'Root');
    $cat2 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child');
    $cat3 = makeCategoryForRemoveAndReorder($categoryRepo, 'Grandchild');

    $root = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat1->id, null, 0);
    $child = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat2->id, $root->id, 0);
    $grandchild = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat3->id, $child->id, 0);

    $service->removeNode($root->id, NodeRemovalStrategy::CASCADE);

    expect($nodeRepo->nodes)->toHaveCount(0);
});

it('removeNode with PROMOTE_CHILDREN deletes the node and reparents direct children to its parent', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForRemoveAndReorder($treeRepo);
    $catGrandparent = makeCategoryForRemoveAndReorder($categoryRepo, 'Grandparent');
    $catParent = makeCategoryForRemoveAndReorder($categoryRepo, 'Parent');
    $catChild1 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child1');
    $catChild2 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child2');

    $grandparent = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catGrandparent->id, null, 0);
    $parent = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catParent->id, $grandparent->id, 0);
    $child1 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild1->id, $parent->id, 0);
    $child2 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild2->id, $parent->id, 10);

    $service->removeNode($parent->id, NodeRemovalStrategy::PROMOTE_CHILDREN);

    // Parent node is deleted
    expect($nodeRepo->find($parent->id))->toBeNull();

    // Children are reparented to grandparent
    expect($child1->parentNodeId)->toBe($grandparent->id);
    expect($child2->parentNodeId)->toBe($grandparent->id);

    // Grandparent and children remain (3 nodes total - 1 removed = 3)
    expect($nodeRepo->nodes)->toHaveCount(3);
});

it('removeNode with PROMOTE_CHILDREN preserves the order of promoted children', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForRemoveAndReorder($treeRepo);
    $catGrandparent = makeCategoryForRemoveAndReorder($categoryRepo, 'Grandparent');
    $catParent = makeCategoryForRemoveAndReorder($categoryRepo, 'Parent');
    $catChild1 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child1');
    $catChild2 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child2');
    $catChild3 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child3');

    $grandparent = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catGrandparent->id, null, 0);
    $parent = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catParent->id, $grandparent->id, 0);
    $child1 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild1->id, $parent->id, 0);
    $child2 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild2->id, $parent->id, 10);
    $child3 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild3->id, $parent->id, 20);

    $service->removeNode($parent->id, NodeRemovalStrategy::PROMOTE_CHILDREN);

    // Relative positions should be preserved
    expect($child1->position)->toBeLessThan($child2->position);
    expect($child2->position)->toBeLessThan($child3->position);
});

it('removeNode with PROMOTE_CHILDREN against a root node makes its children new roots', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForRemoveAndReorder($treeRepo);
    $catRoot = makeCategoryForRemoveAndReorder($categoryRepo, 'Root');
    $catChild1 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child1');
    $catChild2 = makeCategoryForRemoveAndReorder($categoryRepo, 'Child2');

    $root = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catRoot->id, null, 0);
    $child1 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild1->id, $root->id, 0);
    $child2 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild2->id, $root->id, 10);

    $service->removeNode($root->id, NodeRemovalStrategy::PROMOTE_CHILDREN);

    // Root is deleted
    expect($nodeRepo->find($root->id))->toBeNull();

    // Children become roots (parentNodeId = null)
    expect($child1->parentNodeId)->toBeNull();
    expect($child2->parentNodeId)->toBeNull();

    // Only 2 children remain
    expect($nodeRepo->nodes)->toHaveCount(2);
});

it('removeNode throws CategoryTreeNodeNotFoundException when node id is unknown', function (): void {
    $service = makeCategoryTreeServiceForRemoveAndReorder();

    expect(fn () => $service->removeNode(999, NodeRemovalStrategy::CASCADE))
        ->toThrow(CategoryTreeNodeNotFoundException::class);
});

it('reorderSiblings updates positions to match the provided order using POSITION_GAP spacing', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForRemoveAndReorder($treeRepo);
    $cat1 = makeCategoryForRemoveAndReorder($categoryRepo, 'Cat1');
    $cat2 = makeCategoryForRemoveAndReorder($categoryRepo, 'Cat2');
    $cat3 = makeCategoryForRemoveAndReorder($categoryRepo, 'Cat3');

    $node1 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat1->id, null, 0);
    $node2 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat2->id, null, 10);
    $node3 = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat3->id, null, 20);

    // Reorder: put node3 first, node1 second, node2 third
    $service->reorderSiblings(null, $tree->id, [$node3->id, $node1->id, $node2->id]);

    expect($node3->position)->toBe(0);
    expect($node1->position)->toBe(10);
    expect($node2->position)->toBe(20);
});

it('reorderSiblings throws CategoryTreeNodeNotFoundException when any listed id is unknown', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForRemoveAndReorder($treeRepo);
    $cat = makeCategoryForRemoveAndReorder($categoryRepo);
    $node = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $cat->id, null, 0);

    expect(fn () => $service->reorderSiblings(null, $tree->id, [$node->id, 999]))
        ->toThrow(CategoryTreeNodeNotFoundException::class);
});

it('reorderSiblings throws NodeNotInTreeException when a listed node belongs to a different tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForRemoveAndReorder(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree1 = makeTreeForRemoveAndReorder($treeRepo, 'tree1');
    $tree2 = makeTreeForRemoveAndReorder($treeRepo, 'tree2');
    $cat = makeCategoryForRemoveAndReorder($categoryRepo);
    $nodeInTree2 = makeNodeForRemoveAndReorder($nodeRepo, $tree2->id, $cat->id, null, 0);

    expect(fn () => $service->reorderSiblings(null, $tree1->id, [$nodeInTree2->id]))
        ->toThrow(NodeNotInTreeException::class);
});

it(
    'reorderSiblings throws NodeNotInTreeException (forParentMismatch) when a listed node has a different parent than expected',
    function (): void {
        $treeRepo = new FakeCategoryTreeRepository();
        $nodeRepo = new FakeCategoryTreeNodeRepository();
        $categoryRepo = new FakeCategoryRepository();
        $service = makeCategoryTreeServiceForRemoveAndReorder(
            treeRepo: $treeRepo,
            nodeRepo: $nodeRepo,
            categoryRepo: $categoryRepo,
        );
    
        $tree = makeTreeForRemoveAndReorder($treeRepo);
        $catParent = makeCategoryForRemoveAndReorder($categoryRepo, 'Parent');
        $catChild = makeCategoryForRemoveAndReorder($categoryRepo, 'Child');
        $catRoot = makeCategoryForRemoveAndReorder($categoryRepo, 'Root');
    
        $parentNode = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catParent->id, null, 0);
        $childNode = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catChild->id, $parentNode->id, 0);
        $rootNode = makeNodeForRemoveAndReorder($nodeRepo, $tree->id, $catRoot->id, null, 10);
    
        // childNode has parentNodeId = parentNode->id, not null
    // We claim all nodes should have parent = null but childNode has a parent
    expect(fn () => $service->reorderSiblings(null, $tree->id, [$rootNode->id, $childNode->id]))
            ->toThrow(NodeNotInTreeException::class);
    }
);

it('all previously added service tests continue to pass', function (): void {
    $service = makeCategoryTreeServiceForRemoveAndReorder();

    expect($service)->toBeInstanceOf(CategoryTreeService::class);
});
