<?php

declare(strict_types=1);

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

    expect($module['bindings'])->toHaveKey(\Markommerce\Catalog\Contracts\ProductRepositoryInterface::class);
    expect($module['bindings'][\Markommerce\Catalog\Contracts\ProductRepositoryInterface::class])
        ->toBe(\Markommerce\Catalog\Repositories\ProductRepository::class);
});

it('binds CategoryRepositoryInterface to the concrete CategoryRepository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(\Markommerce\Catalog\Contracts\CategoryRepositoryInterface::class);
    expect($module['bindings'][\Markommerce\Catalog\Contracts\CategoryRepositoryInterface::class])
        ->toBe(\Markommerce\Catalog\Repositories\CategoryRepository::class);
});

it('binds ProductCategoryAssignmentRepositoryInterface to the concrete assignment repository', function (): void {
    $module = readCatalogModule();

    expect($module['bindings'])->toHaveKey(\Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface::class);
    expect($module['bindings'][\Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface::class])
        ->toBe(\Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository::class);
});
