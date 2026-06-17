<?php

declare(strict_types=1);

use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\Prepend;

function categoryFacetsExtensionLoad(): LayoutExtension
{
    return require dirname(__DIR__, 3) . '/layout/extensions/category_facets.php';
}

it('registers a category-page layout extension targeting the CategoryController show handle', function (): void {
    $extension = categoryFacetsExtensionLoad();

    expect($extension)->toBeInstanceOf(LayoutExtension::class)
        ->and($extension->handle)->toBe([CategoryController::class, 'show'])
        ->and($extension->operations)->not->toBeEmpty();
});

it('places the facet sidebar into the sidebar-left slot', function (): void {
    $extension = categoryFacetsExtensionLoad();

    $prependOps = array_values(array_filter(
        $extension->operations,
        fn (mixed $op): bool => $op instanceof Prepend,
    ));

    expect($prependOps)->not->toBeEmpty();
    expect($prependOps[0]->slotPath)->toBe('sidebar-left');
});

it('still feeds the filter query param into the product grid placement', function (): void {
    $extension = categoryFacetsExtensionLoad();

    $mergeOps = array_values(array_filter(
        $extension->operations,
        fn (mixed $op): bool => $op instanceof MergeProps,
    ));

    expect($mergeOps)->not->toBeEmpty();
    expect($mergeOps[0]->name)->toBe('catalog.product_grid');
    expect($mergeOps[0]->props)->toHaveKey('filter');
});
