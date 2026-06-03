<?php

declare(strict_types=1);

// __DIR__ = packages/catalog-storefront-scope/tests/Unit/Component
// dirname(__DIR__, 1) = packages/catalog-storefront-scope/tests/Unit
// dirname(__DIR__, 2) = packages/catalog-storefront-scope/tests
// dirname(__DIR__, 3) = packages/catalog-storefront-scope
// dirname(__DIR__, 4) = packages/

it('relocates packages/catalog-scope/src/Component/ScopedProductGridComponent.php to packages/catalog-storefront-scope/src/Component/ScopedProductGridComponent.php with namespace Markommerce\\CatalogStorefrontScope\\Component', function (): void {
    $targetPath = dirname(__DIR__, 3) . '/src/Component/ScopedProductGridComponent.php';

    expect(file_exists($targetPath))->toBeTrue();

    $contents = file_get_contents($targetPath);

    expect($contents)->toContain('namespace Markommerce\CatalogStorefrontScope\Component;');
    expect($contents)->not->toContain('namespace Markommerce\CatalogScope\Component;');

    $sourcePath = dirname(__DIR__, 4) . '/catalog-scope/src/Component/ScopedProductGridComponent.php';
    expect(file_exists($sourcePath))->toBeFalse();
});

it('relocates the matching test file to packages/catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php with updated imports', function (): void {
    $targetPath = __DIR__ . '/ScopedProductGridComponentTest.php';

    expect(file_exists($targetPath))->toBeTrue();

    $contents = file_get_contents($targetPath);

    expect($contents)->toContain('Markommerce\CatalogStorefrontScope\Component\ScopedProductGridComponent');
    expect($contents)->not->toContain('Markommerce\CatalogScope\Component\ScopedProductGridComponent');

    $sourcePath = dirname(__DIR__, 4) . '/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php';
    expect(file_exists($sourcePath))->toBeFalse();
});

it('updates the PreferenceDiscovery test inside the moved test to construct a ModuleManifest with name=markommerce/catalog-storefront-scope and the corresponding path', function (): void {
    $targetPath = __DIR__ . '/ScopedProductGridComponentTest.php';

    expect(file_exists($targetPath))->toBeTrue();

    $contents = file_get_contents($targetPath);

    expect($contents)->toContain("name: 'markommerce/catalog-storefront-scope'");
    expect($contents)->not->toContain("name: 'markommerce/catalog-scope'");
});

it('removes markommerce/catalog-storefront from packages/catalog-scope/composer.json require (added temporarily in task 002)', function (): void {
    $composerPath = dirname(__DIR__, 4) . '/catalog-scope/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->not->toHaveKey('markommerce/catalog-storefront');
});

it('asserts catalog-scope\'s composer.json no longer lists markommerce/catalog-storefront in require or require-dev (regression guard)', function (): void {
    $composerPath = dirname(__DIR__, 4) . '/catalog-scope/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect(array_key_exists('markommerce/catalog-storefront', $composer['require'] ?? []))->toBeFalse();
    expect(array_key_exists('markommerce/catalog-storefront', $composer['require-dev'] ?? []))->toBeFalse();
});

it('asserts no file under packages/catalog-scope references Markommerce\\CatalogStorefront\\ or Markommerce\\CatalogStorefrontScope\\ after the move', function (): void {
    $catalogScopePath = dirname(__DIR__, 4) . '/catalog-scope';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($catalogScopePath, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        expect($contents)->not->toContain(
            'Markommerce\CatalogStorefront\\',
            "File {$file->getPathname()} should not reference Markommerce\\CatalogStorefront\\",
        );
        expect($contents)->not->toContain(
            'Markommerce\CatalogStorefrontScope\\',
            "File {$file->getPathname()} should not reference Markommerce\\CatalogStorefrontScope\\",
        );
    }
});

it('asserts no Markommerce\\CatalogScope\\Component namespace remains in packages/ after the move', function (): void {
    $packagesPath = dirname(__DIR__, 4);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packagesPath, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        expect($contents)->not->toContain(
            'Markommerce\CatalogScope\Component',
            "File {$file->getPathname()} should not reference Markommerce\\CatalogScope\\Component",
        );
    }
});
