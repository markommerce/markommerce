<?php

declare(strict_types=1);

use Marko\Core\Application;

it('it loads cleanly in a Pest feature test that boots a minimal Marko app', function (): void {
    $baseDir = sys_get_temp_dir() . '/markommerce-module-boot-test-' . bin2hex(random_bytes(8));
    $vendorDir = $baseDir . '/vendor';

    // Create the markommerce/frontend module in the temp vendor dir
    bootstrapCreateModule(
        path: $vendorDir . '/markommerce/frontend',
        name: 'markommerce/frontend',
    );

    $app = new Application(
        vendorPath: $vendorDir,
        modulesPath: '',
        appPath: '',
    );

    // Should not throw
    $app->initialize();

    expect($app->modules)->toHaveCount(1);

    bootstrapCleanupDirectory($baseDir);
});

// Helper functions for the feature test

function bootstrapCleanupDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            bootstrapCleanupDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function bootstrapCreateModule(string $path, string $name): void
{
    mkdir($path, 0755, true);

    $composerData = [
        'name' => $name,
        'version' => '1.0.0',
        'extra' => [
            'marko' => [
                'module' => true,
            ],
        ],
    ];
    file_put_contents($path . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));
}
