<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Markommerce\Layout\Discovery\LayoutDiscovery;

it('it discovers the layout-demo layouts through LayoutDiscovery from the new path', function (): void {
    $layoutDemoPath = dirname(__DIR__, 2);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/layout-demo',
            version: '1.0.0',
            path: $layoutDemoPath,
            source: 'vendor',
        ),
    ]);

    $discovery = new LayoutDiscovery($moduleRepository);
    $result = $discovery->discover();

    expect($result->layouts)->not->toBeEmpty();

    $handles = array_map(
        fn ($discovered) => $discovered->layout->handle,
        $result->layouts,
    );

    $found = array_any($handles, fn ($handle) => $handle === [\Markommerce\LayoutDemo\Controller\LayoutDemoController::class, 'show']);
    expect($found)->toBeTrue();
});

it('it discovers the layout-demo extension through LayoutDiscovery from the new path', function (): void {
    $layoutDemoPath = dirname(__DIR__, 2);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/layout-demo',
            version: '1.0.0',
            path: $layoutDemoPath,
            source: 'vendor',
        ),
    ]);

    $discovery = new LayoutDiscovery($moduleRepository);
    $result = $discovery->discover();

    expect($result->extensions)->not->toBeEmpty();
});
