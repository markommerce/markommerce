<?php

declare(strict_types=1);

it('asserts every package that has resources/css/*.css files also has a package.json', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $cssFiles = glob($packageDir . '/resources/css/**/*.css', GLOB_BRACE) ?: [];

        if (count($cssFiles) === 0) {
            continue;
        }

        expect($packageDir . '/package.json')
            ->toBeFile(basename($packageDir) . ' has resources/css/*.css files but is missing package.json');
    }
});

it('asserts every package that ships CSS exposes those files via package.json exports', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $cssFiles = glob($packageDir . '/resources/css/**/*.css', GLOB_BRACE) ?: [];

        if (count($cssFiles) === 0) {
            continue;
        }

        $packageJsonPath = $packageDir . '/package.json';

        if (!is_file($packageJsonPath)) {
            continue;
        }

        $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);
        $exports = $packageJson['exports'] ?? [];

        expect($exports)
            ->not->toBeEmpty(basename($packageDir) . '/package.json is missing an exports map for its CSS files');
    }
});

it('asserts catalog-storefront/package.json specifically exports ./css/components/*.css', function (): void {
    $packageJsonPath = __DIR__ . '/../../packages/catalog-storefront/package.json';

    expect($packageJsonPath)->toBeFile();

    $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);
    $exports = $packageJson['exports'] ?? [];

    expect($exports)->toHaveKey('./css/components/*.css');
    expect($exports['./css/components/*.css'])->toBe('./resources/css/components/*.css');
});
