<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\Catalog\Repositories\CategoryTreeNodeRepository;
use Markommerce\Catalog\Repositories\CategoryTreeRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
use Markommerce\Catalog\Repositories\ProductRepository;

/**
 * @return array<string, mixed>
 */
function readCatalogModule(): array
{
    $path = __DIR__ . '/../../module.php';

    expect(file_exists($path))->toBeTrue();

    /** @var array<string, mixed> */
    return require $path;
}

it('module.php returns an array with a bindings key', function (): void {
    $module = readCatalogModule();

    expect($module)->toBeArray();
    expect($module)->toHaveKey('bindings');
});

it('binds ProductRepositoryInterface to the concrete ProductRepository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(ProductRepositoryInterface::class);
    expect($module['bindings'][ProductRepositoryInterface::class])
        ->toBe(ProductRepository::class);
});

it('binds CategoryRepositoryInterface to the concrete CategoryRepository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(CategoryRepositoryInterface::class);
    expect($module['bindings'][CategoryRepositoryInterface::class])
        ->toBe(CategoryRepository::class);
});

it('binds ProductCategoryAssignmentRepositoryInterface to the concrete assignment repository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(ProductCategoryAssignmentRepositoryInterface::class);
    expect($module['bindings'][ProductCategoryAssignmentRepositoryInterface::class])
        ->toBe(ProductCategoryAssignmentRepository::class);
});

it('module.php binds CategoryTreeRepositoryInterface to CategoryTreeRepository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(CategoryTreeRepositoryInterface::class);
    expect($module['bindings'][CategoryTreeRepositoryInterface::class])
        ->toBe(CategoryTreeRepository::class);
});

it('module.php binds CategoryTreeNodeRepositoryInterface to CategoryTreeNodeRepository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(CategoryTreeNodeRepositoryInterface::class);
    expect($module['bindings'][CategoryTreeNodeRepositoryInterface::class])
        ->toBe(CategoryTreeNodeRepository::class);
});

it('module.php binds CategoryTreeMarketAssignmentRepositoryInterface to CategoryTreeMarketAssignmentRepository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(CategoryTreeMarketAssignmentRepositoryInterface::class);
    expect($module['bindings'][CategoryTreeMarketAssignmentRepositoryInterface::class])
        ->toBe(CategoryTreeMarketAssignmentRepository::class);
});

it('module.php preserves the existing pre-tree bindings (ProductRepositoryInterface, CategoryRepositoryInterface, ProductCategoryAssignmentRepositoryInterface)', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(ProductRepositoryInterface::class);
    expect($module['bindings'][ProductRepositoryInterface::class])->toBe(ProductRepository::class);

    expect($module['bindings'])->toHaveKey(CategoryRepositoryInterface::class);
    expect($module['bindings'][CategoryRepositoryInterface::class])->toBe(CategoryRepository::class);

    expect($module['bindings'])->toHaveKey(ProductCategoryAssignmentRepositoryInterface::class);
    expect($module['bindings'][ProductCategoryAssignmentRepositoryInterface::class])
        ->toBe(ProductCategoryAssignmentRepository::class);
});
