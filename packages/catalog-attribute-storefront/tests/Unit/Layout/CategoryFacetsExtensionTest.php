<?php

declare(strict_types=1);

use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\Layout\LayoutExtension;

it('registers a category-page layout extension targeting the CategoryController show handle', function (): void {
    $extension = require dirname(__DIR__, 3) . '/layout/extensions/category_facets.php';

    expect($extension)->toBeInstanceOf(LayoutExtension::class)
        ->and($extension->handle)->toBe([CategoryController::class, 'show'])
        ->and($extension->operations)->not->toBeEmpty();
});
