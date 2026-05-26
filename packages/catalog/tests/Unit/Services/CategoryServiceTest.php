<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTreeNode;
use Markommerce\Catalog\Exceptions\CategoryHasPlacementsException;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Services\CategoryService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeNodeRepository;

it('delete removes a category that has no placements', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $nodeRepository = new FakeCategoryTreeNodeRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $service = new CategoryService(
        categoryRepository: $categoryRepository,
        categoryTreeNodeRepository: $nodeRepository,
    );

    $service->delete($category->id);

    expect($categoryRepository->categories)->toHaveCount(0);
});

it('delete throws CategoryNotFoundException when category id is unknown', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $nodeRepository = new FakeCategoryTreeNodeRepository();

    $service = new CategoryService(
        categoryRepository: $categoryRepository,
        categoryTreeNodeRepository: $nodeRepository,
    );

    expect(fn () => $service->delete(999))
        ->toThrow(CategoryNotFoundException::class);
});

it('delete throws CategoryHasPlacementsException when the category is placed in any tree', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $nodeRepository = new FakeCategoryTreeNodeRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $node = new CategoryTreeNode();
    $node->categoryId = $category->id;
    $node->treeId = 1;
    $node->position = 0;
    $nodeRepository->save($node);

    $service = new CategoryService(
        categoryRepository: $categoryRepository,
        categoryTreeNodeRepository: $nodeRepository,
    );

    expect(fn () => $service->delete($category->id))
        ->toThrow(CategoryHasPlacementsException::class);
});

it('CategoryHasPlacementsException carries the placement count for diagnostics', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $nodeRepository = new FakeCategoryTreeNodeRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $node1 = new CategoryTreeNode();
    $node1->categoryId = $category->id;
    $node1->treeId = 1;
    $node1->position = 0;
    $nodeRepository->save($node1);

    $node2 = new CategoryTreeNode();
    $node2->categoryId = $category->id;
    $node2->treeId = 2;
    $node2->position = 0;
    $nodeRepository->save($node2);

    $service = new CategoryService(
        categoryRepository: $categoryRepository,
        categoryTreeNodeRepository: $nodeRepository,
    );

    try {
        $service->delete($category->id);
        $this->fail('Expected CategoryHasPlacementsException was not thrown');
    } catch (CategoryHasPlacementsException $e) {
        expect($e->getMessage())->toContain('2');
    }
});
