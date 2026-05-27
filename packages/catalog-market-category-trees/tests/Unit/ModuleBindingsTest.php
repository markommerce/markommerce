<?php

declare(strict_types=1);

use Markommerce\CatalogMarketCategoryTrees\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarketCategoryTrees\Repositories\CategoryTreeMarketAssignmentRepository;

/**
 * @return array<string, mixed>
 */
function readCatalogMarketCategoryTreesModule(): array
{
    $path = __DIR__ . '/../../module.php';

    expect(file_exists($path))->toBeTrue();

    /** @var array<string, mixed> */
    return require $path;
}

it('registers CategoryTreeMarketAssignmentRepositoryInterface => CategoryTreeMarketAssignmentRepository in the new module.php bindings', function (): void {
    $module = readCatalogMarketCategoryTreesModule();

    expect($module)->toBeArray();
    expect($module)->toHaveKey('bindings');
    expect($module['bindings'])->toHaveKey(CategoryTreeMarketAssignmentRepositoryInterface::class);
    expect($module['bindings'][CategoryTreeMarketAssignmentRepositoryInterface::class])
        ->toBe(CategoryTreeMarketAssignmentRepository::class);
});
