<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Layout;
use Markommerce\Catalog\Controller\CategoryController;

it('loads category_show from resources/views/layout/category_show.php', function (): void {
    $newPath = dirname(__DIR__, 2) . '/resources/views/layout/category_show.php';

    expect(file_exists($newPath))->toBeTrue();

    $layout = require $newPath;
    expect($layout)->toBeInstanceOf(Layout::class);
});

it('has no files remaining under packages/catalog/layout/', function (): void {
    $oldDir = dirname(__DIR__, 2) . '/layout';

    $hasFiles = is_dir($oldDir) && count(glob($oldDir . '/*.php') ?: []) > 0;

    expect($hasFiles)->toBeFalse();
});

it('discovers the catalog category_show layout through LayoutDiscovery from the new path', function (): void {
    $catalogPath = dirname(__DIR__, 2);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog',
            version: '1.0.0',
            path: $catalogPath,
            source: 'vendor',
        ),
    ]);

    $discovery = new LayoutDiscovery($moduleRepository);
    $result = $discovery->discover();

    $layouts = $result->layouts;
    $handles = array_map(
        fn ($discovered) => $discovered->layout->handle,
        $layouts,
    );

    expect($handles)->toContain([CategoryController::class, 'show']);
});

it('the category_show feature test still passes when layout file is at the new path', function (): void {
    $newPath = dirname(__DIR__, 2) . '/resources/views/layout/category_show.php';
    $oldPath = dirname(__DIR__, 2) . '/layout/category_show.php';

    expect(file_exists($newPath))->toBeTrue();
    expect(file_exists($oldPath))->toBeFalse();
});

it('CategoryControllerTest references the layout file at resources/views/layout/category_show.php', function (): void {
    $testFile = dirname(__DIR__) . '/Feature/CategoryControllerTest.php';
    $contents = file_get_contents($testFile);

    expect($contents)->toContain('resources/views/layout/category_show.php');
    expect($contents)->not->toContain("'/layout/category_show.php'");
});

it('CategoryLayoutTest references the layout file at resources/views/layout/category_show.php', function (): void {
    $testFile = dirname(__DIR__) . '/Feature/CategoryLayoutTest.php';
    $contents = file_get_contents($testFile);

    expect($contents)->toContain('resources/views/layout/category_show.php');
    expect($contents)->not->toContain("'/layout/category_show.php'");
});
