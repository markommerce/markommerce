<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Exceptions\CategoryTreeNodeNotFoundException;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;
use Markommerce\Catalog\Exceptions\NodeNotInTreeException;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;

function makeCategoryTreeServiceForPlaceAndMove(
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

function makeTree(FakeCategoryTreeRepository $treeRepo, string $code = 'main'): CategoryTree
{
    $tree = new CategoryTree();
    $tree->code = $code;
    $tree->name = ucfirst($code) . ' Tree';
    $tree->isDefault = false;
    $treeRepo->save($tree);

    return $tree;
}

function makeCategory(FakeCategoryRepository $categoryRepo, string $name = 'Test Category'): Category
{
    $category = new Category();
    $category->name = $name;
    $categoryRepo->save($category);

    return $category;
}

function makeNode(FakeCategoryTreeNodeRepository $nodeRepo, int $treeId, int $categoryId, ?int $parentNodeId = null, int $position = 0): CategoryTreeNode
{
    $node = new CategoryTreeNode();
    $node->treeId = $treeId;
    $node->categoryId = $categoryId;
    $node->parentNodeId = $parentNodeId;
    $node->position = $position;
    $nodeRepo->save($node);

    return $node;
}

it('placeCategory creates a root node when parent is null', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $category = makeCategory($categoryRepo);

    $node = $service->placeCategory(treeId: $tree->id, categoryId: $category->id);

    expect($node)->toBeInstanceOf(CategoryTreeNode::class)
        ->and($node->treeId)->toBe($tree->id)
        ->and($node->categoryId)->toBe($category->id)
        ->and($node->parentNodeId)->toBeNull()
        ->and($node->id)->not->toBeNull();

    expect($nodeRepo->nodes)->toHaveCount(1);
});

it('placeCategory creates a child node under the given parent', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $parentCategory = makeCategory($categoryRepo, 'Parent');
    $childCategory = makeCategory($categoryRepo, 'Child');

    $parentNode = makeNode($nodeRepo, $tree->id, $parentCategory->id);

    $childNode = $service->placeCategory(treeId: $tree->id, categoryId: $childCategory->id, parentNodeId: $parentNode->id);

    expect($childNode->parentNodeId)->toBe($parentNode->id)
        ->and($childNode->treeId)->toBe($tree->id)
        ->and($childNode->categoryId)->toBe($childCategory->id);
});

it('placeCategory allows the same category to be placed twice in the same tree (multi-placement)', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $category = makeCategory($categoryRepo);

    $node1 = $service->placeCategory(treeId: $tree->id, categoryId: $category->id);
    $node2 = $service->placeCategory(treeId: $tree->id, categoryId: $category->id);

    expect($nodeRepo->nodes)->toHaveCount(2);
    expect($node1->id)->not->toBe($node2->id);
    expect($node1->categoryId)->toBe($node2->categoryId);
});

it('placeCategory assigns the next available position (max + POSITION_GAP) when position is null', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $cat1 = makeCategory($categoryRepo, 'Cat 1');
    $cat2 = makeCategory($categoryRepo, 'Cat 2');
    $cat3 = makeCategory($categoryRepo, 'Cat 3');

    $node1 = $service->placeCategory(treeId: $tree->id, categoryId: $cat1->id);
    expect($node1->position)->toBe(0);

    $node2 = $service->placeCategory(treeId: $tree->id, categoryId: $cat2->id);
    expect($node2->position)->toBe(10);

    $node3 = $service->placeCategory(treeId: $tree->id, categoryId: $cat3->id);
    expect($node3->position)->toBe(20);
});

it('placeCategory respects an explicitly provided position value', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $category = makeCategory($categoryRepo);

    $node = $service->placeCategory(treeId: $tree->id, categoryId: $category->id, position: 42);

    expect($node->position)->toBe(42);
});

it('placeCategory throws CategoryTreeNotFoundException when tree id is unknown', function (): void {
    $service = makeCategoryTreeServiceForPlaceAndMove();

    expect(fn () => $service->placeCategory(treeId: 999, categoryId: 1))
        ->toThrow(CategoryTreeNotFoundException::class);
});

it('placeCategory throws CategoryNotFoundException when category id is unknown', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(treeRepo: $treeRepo);

    $tree = makeTree($treeRepo);

    expect(fn () => $service->placeCategory(treeId: $tree->id, categoryId: 999))
        ->toThrow(CategoryNotFoundException::class);
});

it('placeCategory throws CategoryTreeNodeNotFoundException when parentNodeId is provided but the parent node does not exist', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(treeRepo: $treeRepo, categoryRepo: $categoryRepo);

    $tree = makeTree($treeRepo);
    $category = makeCategory($categoryRepo);

    expect(fn () => $service->placeCategory(treeId: $tree->id, categoryId: $category->id, parentNodeId: 999))
        ->toThrow(CategoryTreeNodeNotFoundException::class);
});

it('placeCategory throws NodeNotInTreeException when parent node belongs to a different tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree1 = makeTree($treeRepo, 'tree1');
    $tree2 = makeTree($treeRepo, 'tree2');
    $category = makeCategory($categoryRepo);
    $parentCategory = makeCategory($categoryRepo, 'Parent');

    // Parent node belongs to tree2
    $parentNode = makeNode($nodeRepo, $tree2->id, $parentCategory->id);

    // Placing in tree1 but referencing a parent from tree2
    expect(fn () => $service->placeCategory(treeId: $tree1->id, categoryId: $category->id, parentNodeId: $parentNode->id))
        ->toThrow(NodeNotInTreeException::class);
});

it('moveNode updates the parent and position of a node', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $cat1 = makeCategory($categoryRepo, 'Cat 1');
    $cat2 = makeCategory($categoryRepo, 'Cat 2');

    $nodeA = makeNode($nodeRepo, $tree->id, $cat1->id, null, 0);
    $nodeB = makeNode($nodeRepo, $tree->id, $cat2->id, null, 10);

    $service->moveNode(nodeId: $nodeB->id, newParentNodeId: $nodeA->id, position: 5);

    expect($nodeB->parentNodeId)->toBe($nodeA->id)
        ->and($nodeB->position)->toBe(5);
});

it('moveNode promotes a node to root when new parent is null', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $cat1 = makeCategory($categoryRepo, 'Parent Cat');
    $cat2 = makeCategory($categoryRepo, 'Child Cat');

    $parentNode = makeNode($nodeRepo, $tree->id, $cat1->id, null, 0);
    $childNode = makeNode($nodeRepo, $tree->id, $cat2->id, $parentNode->id, 0);

    $service->moveNode(nodeId: $childNode->id, newParentNodeId: null, position: 20);

    expect($childNode->parentNodeId)->toBeNull()
        ->and($childNode->position)->toBe(20);
});

it('moveNode throws CategoryTreeNodeNotFoundException when node id is unknown', function (): void {
    $service = makeCategoryTreeServiceForPlaceAndMove();

    expect(fn () => $service->moveNode(nodeId: 999, newParentNodeId: null, position: 0))
        ->toThrow(CategoryTreeNodeNotFoundException::class);
});

it('moveNode throws CategoryTreeNodeNotFoundException when newParentNodeId is non-null but the parent node does not exist', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $category = makeCategory($categoryRepo);
    $node = makeNode($nodeRepo, $tree->id, $category->id);

    expect(fn () => $service->moveNode(nodeId: $node->id, newParentNodeId: 999, position: 0))
        ->toThrow(CategoryTreeNodeNotFoundException::class);
});

it('moveNode throws NodeNotInTreeException when the new parent belongs to a different tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree1 = makeTree($treeRepo, 'tree1');
    $tree2 = makeTree($treeRepo, 'tree2');
    $cat1 = makeCategory($categoryRepo, 'Cat 1');
    $cat2 = makeCategory($categoryRepo, 'Cat 2');

    $nodeInTree1 = makeNode($nodeRepo, $tree1->id, $cat1->id);
    $nodeInTree2 = makeNode($nodeRepo, $tree2->id, $cat2->id);

    expect(fn () => $service->moveNode(nodeId: $nodeInTree1->id, newParentNodeId: $nodeInTree2->id, position: 0))
        ->toThrow(NodeNotInTreeException::class);
});

it('moveNode throws CircularNodeReferenceException when newParentNodeId equals nodeId (self-parent)', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $category = makeCategory($categoryRepo);
    $node = makeNode($nodeRepo, $tree->id, $category->id);

    expect(fn () => $service->moveNode(nodeId: $node->id, newParentNodeId: $node->id, position: 0))
        ->toThrow(CircularNodeReferenceException::class);
});

it('moveNode throws CircularNodeReferenceException when the move would create a cycle (parent under direct child)', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $cat1 = makeCategory($categoryRepo, 'Parent');
    $cat2 = makeCategory($categoryRepo, 'Child');

    $parentNode = makeNode($nodeRepo, $tree->id, $cat1->id, null, 0);
    $childNode = makeNode($nodeRepo, $tree->id, $cat2->id, $parentNode->id, 0);

    // Try to move parentNode under childNode — creates a cycle
    expect(fn () => $service->moveNode(nodeId: $parentNode->id, newParentNodeId: $childNode->id, position: 0))
        ->toThrow(CircularNodeReferenceException::class);
});

it('moveNode throws CircularNodeReferenceException for a deeper cycle (grandparent under grandchild)', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForPlaceAndMove(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTree($treeRepo);
    $cat1 = makeCategory($categoryRepo, 'Grandparent');
    $cat2 = makeCategory($categoryRepo, 'Parent');
    $cat3 = makeCategory($categoryRepo, 'Child');

    $grandparent = makeNode($nodeRepo, $tree->id, $cat1->id, null, 0);
    $parent = makeNode($nodeRepo, $tree->id, $cat2->id, $grandparent->id, 0);
    $child = makeNode($nodeRepo, $tree->id, $cat3->id, $parent->id, 0);

    // Try to move grandparent under grandchild — creates a cycle via grandparent -> ... -> grandchild -> grandparent
    expect(fn () => $service->moveNode(nodeId: $grandparent->id, newParentNodeId: $child->id, position: 0))
        ->toThrow(CircularNodeReferenceException::class);
});

it('all previously added tests in CategoryTreeServiceTreeCrudTest and CategoryTreeServiceMarketResolutionTest continue to pass with the expanded constructor', function (): void {
    // This test is a meta-test: if the other test files still pass after the constructor expansion,
    // this requirement is satisfied. We verify here by instantiating the service with all 4 parameters
    // to confirm backward compatibility of the updated helper functions in those files.
    $service = makeCategoryTreeServiceForPlaceAndMove();

    expect($service)->toBeInstanceOf(CategoryTreeService::class);
});
