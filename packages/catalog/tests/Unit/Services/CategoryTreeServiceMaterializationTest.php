<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Enum\NodeRemovalStrategy;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;

function makeCategoryTreeServiceForMaterialization(
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

function makeTreeForMaterialization(FakeCategoryTreeRepository $treeRepo, string $code = 'main'): CategoryTree
{
    $tree = new CategoryTree();
    $tree->code = $code;
    $tree->name = ucfirst($code) . ' Tree';
    $tree->isDefault = false;
    $treeRepo->save($tree);

    return $tree;
}

function makeCategoryForMaterialization(FakeCategoryRepository $categoryRepo, string $name = 'Test Category'): Category
{
    $category = new Category();
    $category->name = $name;
    $categoryRepo->save($category);

    return $category;
}

function makeNodeForMaterialization(FakeCategoryTreeNodeRepository $nodeRepo, int $treeId, int $categoryId, ?int $parentNodeId = null, int $position = 0): CategoryTreeNode
{
    $node = new CategoryTreeNode();
    $node->treeId = $treeId;
    $node->categoryId = $categoryId;
    $node->parentNodeId = $parentNodeId;
    $node->position = $position;
    $nodeRepo->save($node);

    return $node;
}

it('getMaterializedTree returns roots in position order', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForMaterialization(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForMaterialization($treeRepo);
    $cat1 = makeCategoryForMaterialization($categoryRepo, 'Cat1');
    $cat2 = makeCategoryForMaterialization($categoryRepo, 'Cat2');
    $cat3 = makeCategoryForMaterialization($categoryRepo, 'Cat3');

    $node1 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat1->id, null, 20);
    $node2 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat2->id, null, 0);
    $node3 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat3->id, null, 10);

    $result = $service->getMaterializedTree($tree->id);

    expect($result)->toHaveCount(3)
        ->and($result[0]['node']->id)->toBe($node2->id)
        ->and($result[1]['node']->id)->toBe($node3->id)
        ->and($result[2]['node']->id)->toBe($node1->id);
});

it('getMaterializedTree returns children at every depth in position order', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForMaterialization(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForMaterialization($treeRepo);
    $cat1 = makeCategoryForMaterialization($categoryRepo, 'Root');
    $cat2 = makeCategoryForMaterialization($categoryRepo, 'ChildB');
    $cat3 = makeCategoryForMaterialization($categoryRepo, 'ChildA');
    $cat4 = makeCategoryForMaterialization($categoryRepo, 'Grandchild2');
    $cat5 = makeCategoryForMaterialization($categoryRepo, 'Grandchild1');

    $root = makeNodeForMaterialization($nodeRepo, $tree->id, $cat1->id, null, 0);
    $childB = makeNodeForMaterialization($nodeRepo, $tree->id, $cat2->id, $root->id, 20);
    $childA = makeNodeForMaterialization($nodeRepo, $tree->id, $cat3->id, $root->id, 10);
    $grandchild2 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat4->id, $childA->id, 20);
    $grandchild1 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat5->id, $childA->id, 10);

    $result = $service->getMaterializedTree($tree->id);

    expect($result)->toHaveCount(1);

    $rootEntry = $result[0];
    expect($rootEntry['node']->id)->toBe($root->id)
        ->and($rootEntry['children'])->toHaveCount(2);

    $children = $rootEntry['children'];
    expect($children[0]['node']->id)->toBe($childA->id)
        ->and($children[1]['node']->id)->toBe($childB->id);

    $grandchildren = $children[0]['children'];
    expect($grandchildren)->toHaveCount(2)
        ->and($grandchildren[0]['node']->id)->toBe($grandchild1->id)
        ->and($grandchildren[1]['node']->id)->toBe($grandchild2->id);
});

it('getMaterializedTree returns an empty array for a tree with no nodes', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $service = makeCategoryTreeServiceForMaterialization(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
    );

    $tree = makeTreeForMaterialization($treeRepo);

    $result = $service->getMaterializedTree($tree->id);

    expect($result)->toBe([]);
});

it('getMaterializedTree throws CategoryTreeNotFoundException for an unknown tree id', function (): void {
    $service = makeCategoryTreeServiceForMaterialization();

    expect(fn () => $service->getMaterializedTree(999))
        ->toThrow(CategoryTreeNotFoundException::class);
});

it(
    'getMaterializedTree memoises within a request — repeated calls do not re-query the node repository',
    function (): void {
        $treeRepo = new FakeCategoryTreeRepository();
        $nodeRepo = new FakeCategoryTreeNodeRepository();
        $categoryRepo = new FakeCategoryRepository();
        $service = makeCategoryTreeServiceForMaterialization(
            treeRepo: $treeRepo,
            nodeRepo: $nodeRepo,
            categoryRepo: $categoryRepo,
        );
    
        $tree = makeTreeForMaterialization($treeRepo);
        $cat = makeCategoryForMaterialization($categoryRepo, 'Cat1');
        makeNodeForMaterialization($nodeRepo, $tree->id, $cat->id, null, 0);
    
        $callsBefore = count($nodeRepo->callLog);
    
        $service->getMaterializedTree($tree->id);
        $service->getMaterializedTree($tree->id);
    
        $callsAfter = count($nodeRepo->callLog);
    
        expect($callsAfter - $callsBefore)->toBe(1);
    }
);

it('moveNode invalidates the cached materialization for the affected tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForMaterialization(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForMaterialization($treeRepo);
    $cat1 = makeCategoryForMaterialization($categoryRepo, 'Cat1');
    $cat2 = makeCategoryForMaterialization($categoryRepo, 'Cat2');
    $node1 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat1->id, null, 0);
    $node2 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat2->id, null, 10);

    // Populate cache
    $service->getMaterializedTree($tree->id);

    $callsBefore = count($nodeRepo->callLog);

    // Invalidate cache via moveNode
    $service->moveNode(nodeId: $node2->id, newParentNodeId: $node1->id, position: 0);

    // Next call should re-query
    $service->getMaterializedTree($tree->id);

    $callsAfter = count($nodeRepo->callLog);

    expect($callsAfter - $callsBefore)->toBe(1);
});

it('removeNode invalidates the cached materialization for the affected tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForMaterialization(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForMaterialization($treeRepo);
    $cat1 = makeCategoryForMaterialization($categoryRepo, 'Cat1');
    $node1 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat1->id, null, 0);

    // Populate cache
    $service->getMaterializedTree($tree->id);

    $callsBefore = count($nodeRepo->callLog);

    // Invalidate cache via removeNode
    $service->removeNode(nodeId: $node1->id, strategy: NodeRemovalStrategy::CASCADE);

    // Next call should re-query
    $service->getMaterializedTree($tree->id);

    $callsAfter = count($nodeRepo->callLog);

    expect($callsAfter - $callsBefore)->toBe(1);
});

it('reorderSiblings invalidates the cached materialization for the affected tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForMaterialization(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForMaterialization($treeRepo);
    $cat1 = makeCategoryForMaterialization($categoryRepo, 'Cat1');
    $cat2 = makeCategoryForMaterialization($categoryRepo, 'Cat2');
    $node1 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat1->id, null, 0);
    $node2 = makeNodeForMaterialization($nodeRepo, $tree->id, $cat2->id, null, 10);

    // Populate cache
    $service->getMaterializedTree($tree->id);

    $callsBefore = count($nodeRepo->callLog);

    // Invalidate cache via reorderSiblings
    $service->reorderSiblings(parentNodeId: null, treeId: $tree->id, orderedNodeIds: [$node2->id, $node1->id]);

    // Next call should re-query
    $service->getMaterializedTree($tree->id);

    $callsAfter = count($nodeRepo->callLog);

    expect($callsAfter - $callsBefore)->toBe(1);
});

it('placeCategory invalidates the cached materialization for the affected tree', function (): void {
    $treeRepo = new FakeCategoryTreeRepository();
    $nodeRepo = new FakeCategoryTreeNodeRepository();
    $categoryRepo = new FakeCategoryRepository();
    $service = makeCategoryTreeServiceForMaterialization(
        treeRepo: $treeRepo,
        nodeRepo: $nodeRepo,
        categoryRepo: $categoryRepo,
    );

    $tree = makeTreeForMaterialization($treeRepo);
    $cat1 = makeCategoryForMaterialization($categoryRepo, 'Cat1');
    $cat2 = makeCategoryForMaterialization($categoryRepo, 'Cat2');
    makeNodeForMaterialization($nodeRepo, $tree->id, $cat1->id, null, 0);

    // Populate cache
    $service->getMaterializedTree($tree->id);

    $callsBefore = count($nodeRepo->callLog);

    // Invalidate cache via placeCategory
    $service->placeCategory(treeId: $tree->id, categoryId: $cat2->id);

    // Next call should re-query
    $service->getMaterializedTree($tree->id);

    $callsAfter = count($nodeRepo->callLog);

    expect($callsAfter - $callsBefore)->toBe(1);
});
