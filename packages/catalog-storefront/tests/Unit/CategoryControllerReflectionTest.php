<?php

declare(strict_types=1);

use Marko\Routing\Attributes\Get;
use Markommerce\CatalogStorefront\Controller\CategoryController;

it('places a Get route at /catalog/category/{id} on the controller action', function (): void {
    $reflection = new ReflectionClass(CategoryController::class);

    $classGetAttributes = $reflection->getAttributes(Get::class);
    expect($classGetAttributes)->toBeEmpty();

    $method = $reflection->getMethod('show');
    $methodGetAttributes = $method->getAttributes(Get::class);
    expect($methodGetAttributes)->not->toBeEmpty();

    $getAttr = $methodGetAttributes[0]->newInstance();
    expect($getAttr->path)->toBe('/catalog/category/{id}');
});

it(
    'defines the layout for CategoryController show via a layout file instead of a controller attribute',
    function (): void {
        $reflection = new ReflectionClass(CategoryController::class);

        $markoLayoutClass = 'Marko\Layout\Attributes\Layout';
        $attributes = $reflection->getAttributes($markoLayoutClass);
        expect($attributes)->toBeEmpty();

        $layoutPath = dirname(__DIR__, 2) . '/layout/category_show.php';
        expect(file_exists($layoutPath))->toBeTrue();
    },
);

it('removes the standalone resources/views/category.latte template', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/category.latte';

    expect(file_exists($templatePath))->toBeFalse();
});
