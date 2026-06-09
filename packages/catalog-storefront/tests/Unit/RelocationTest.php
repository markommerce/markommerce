<?php

declare(strict_types=1);

it(
    'relocates Controller, Component, Context, Data, and Iteration source files from packages/catalog/src to packages/catalog-storefront/src with namespace Markommerce\\CatalogStorefront\\…',
    function (): void {
        $storefrontSrc = dirname(__DIR__, 2) . '/src';

        // Controller
        expect(file_exists($storefrontSrc . '/Controller/CategoryController.php'))->toBeTrue();
        $controllerContents = file_get_contents($storefrontSrc . '/Controller/CategoryController.php');
        expect($controllerContents)->toContain('namespace Markommerce\\CatalogStorefront\\Controller;');

        // Components
        expect(file_exists($storefrontSrc . '/Component/ProductGridComponent.php'))->toBeTrue();
        expect(file_exists($storefrontSrc . '/Component/ProductCard.php'))->toBeTrue();
        expect(file_exists($storefrontSrc . '/Component/StockBadge.php'))->toBeTrue();
        $gridContents = file_get_contents($storefrontSrc . '/Component/ProductGridComponent.php');
        expect($gridContents)->toContain('namespace Markommerce\\CatalogStorefront\\Component;');

        // Context
        expect(file_exists($storefrontSrc . '/Context/CategoryDataProvider.php'))->toBeTrue();
        expect(file_exists($storefrontSrc . '/Context/CategoryToken.php'))->toBeTrue();
        $providerContents = file_get_contents($storefrontSrc . '/Context/CategoryDataProvider.php');
        expect($providerContents)->toContain('namespace Markommerce\\CatalogStorefront\\Context;');

        // Data
        expect(file_exists($storefrontSrc . '/Data/ProductGridData.php'))->toBeTrue();
        expect(file_exists($storefrontSrc . '/Data/ProductCardData.php'))->toBeTrue();
        expect(file_exists($storefrontSrc . '/Data/StockBadgeData.php'))->toBeTrue();
        $gridDataContents = file_get_contents($storefrontSrc . '/Data/ProductGridData.php');
        expect($gridDataContents)->toContain('namespace Markommerce\\CatalogStorefront\\Data;');

        // Iteration
        expect(file_exists($storefrontSrc . '/Iteration/ProductIteration.php'))->toBeTrue();
        $iterationContents = file_get_contents($storefrontSrc . '/Iteration/ProductIteration.php');
        expect($iterationContents)->toContain('namespace Markommerce\\CatalogStorefront\\Iteration;');
    },
);

it(
    'relocates the layout file from packages/catalog/layout/category_show.php to packages/catalog-storefront/layout/category_show.php',
    function (): void {
        $storefrontRoot = dirname(__DIR__, 2);

        expect(file_exists($storefrontRoot . '/layout/category_show.php'))->toBeTrue();

        // Verify it uses the catalog-storefront namespace classes
        $contents = file_get_contents($storefrontRoot . '/layout/category_show.php');
        expect($contents)->toContain('Markommerce\\CatalogStorefront\\');
    },
);

it(
    'relocates Latte templates, CSS, JS, and package.json from packages/catalog/resources and packages/catalog to packages/catalog-storefront with the npm package renamed to @markommerce/catalog-storefront',
    function (): void {
        $storefrontRoot = dirname(__DIR__, 2);

        // Latte templates
        expect(file_exists($storefrontRoot . '/resources/views/components/product-card.latte'))->toBeTrue();
        expect(file_exists($storefrontRoot . '/resources/views/components/product-grid.latte'))->toBeTrue();
        expect(file_exists($storefrontRoot . '/resources/views/components/product-grid-item.latte'))->toBeTrue();
        expect(file_exists($storefrontRoot . '/resources/views/components/stock-badge.latte'))->toBeTrue();

        // CSS
        expect(file_exists($storefrontRoot . '/resources/css/components/product-card.css'))->toBeTrue();

        // JS
        expect(file_exists($storefrontRoot . '/resources/js/index.ts'))->toBeTrue();
        expect(file_exists($storefrontRoot . '/resources/js/package.test.ts'))->toBeTrue();

        // package.json with renamed package
        expect(file_exists($storefrontRoot . '/package.json'))->toBeTrue();
        $packageJson = json_decode(file_get_contents($storefrontRoot . '/package.json'), true);
        expect($packageJson['name'])->toBe('@markommerce/catalog-storefront');
    },
);

it(
    'rewrites every catalog::components/… template namespace reference to catalog-storefront::components/… in the moved layout file and moved tests',
    function (): void {
        $storefrontRoot = dirname(__DIR__, 2);

        // Layout file
        $layoutContents = file_get_contents($storefrontRoot . '/layout/category_show.php');
        expect($layoutContents)->not->toContain("'catalog::components/");
        expect($layoutContents)->toContain("'catalog-storefront::components/");

        // CategoryControllerTest — migrated to harness (task 020); no inline template strings remain.
        // Invariant: must NOT contain old catalog:: prefix; does not need to contain catalog-storefront::
        // because the harness-style test has no template name literals at all.
        $controllerTestContents = file_get_contents($storefrontRoot . '/tests/Feature/CategoryControllerTest.php');
        expect($controllerTestContents)->not->toContain("'catalog::components/");

        // CategoryLayoutTest — migrated to harness (task 020); no inline template strings remain.
        $layoutTestContents = file_get_contents($storefrontRoot . '/tests/Feature/CategoryLayoutTest.php');
        expect($layoutTestContents)->not->toContain("'catalog::components/");

        // ProductGridComponentTest (unit test, not migrated) still has real template references
        $gridTestContents = file_get_contents($storefrontRoot . '/tests/Unit/Component/ProductGridComponentTest.php');
        expect($gridTestContents)->not->toContain("'catalog::components/");
        expect($gridTestContents)->toContain("'catalog-storefront::components/");
    },
);

it(
    'relocates CategoryControllerTest, CategoryLayoutTest, and ProductGridComponentTest into packages/catalog-storefront/tests with corrected dirname depths and ModuleManifest names',
    function (): void {
        $storefrontRoot = dirname(__DIR__, 2);

        // Tests exist
        expect(file_exists($storefrontRoot . '/tests/Feature/CategoryControllerTest.php'))->toBeTrue();
        expect(file_exists($storefrontRoot . '/tests/Feature/CategoryLayoutTest.php'))->toBeTrue();
        expect(file_exists($storefrontRoot . '/tests/Unit/Component/ProductGridComponentTest.php'))->toBeTrue();

        // ModuleManifest names — CategoryControllerTest and CategoryLayoutTest were migrated
        // to the harness (task 020) and no longer contain inline ModuleManifest declarations.
        // The invariant is that they do NOT reference the old 'markommerce/catalog' name.
        $controllerContents = file_get_contents($storefrontRoot . '/tests/Feature/CategoryControllerTest.php');
        expect($controllerContents)->not->toContain("name: 'markommerce/catalog'");

        $layoutContents = file_get_contents($storefrontRoot . '/tests/Feature/CategoryLayoutTest.php');
        expect($layoutContents)->not->toContain("name: 'markommerce/catalog'");

        $gridContents = file_get_contents($storefrontRoot . '/tests/Unit/Component/ProductGridComponentTest.php');
        expect($gridContents)->toContain("name: 'markommerce/catalog-storefront'");
        expect($gridContents)->not->toContain("name: 'markommerce/catalog'");
    },
);

it(
    'leaves packages/catalog/src with only allowed directories',
    function (): void {
        $catalogSrc = dirname(__DIR__, 3) . '/catalog/src';

        $allowedDirs = ['Config', 'Contracts', 'Entity', 'Enum', 'Exceptions', 'Pagination', 'Pricing', 'Repositories', 'Services', 'Sorting'];

        $dirs = array_values(array_filter(
            scandir($catalogSrc),
            fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($catalogSrc . '/' . $entry),
        ));

        expect($dirs)->toBe($allowedDirs);
    },
);

it(
    'asserts no Markommerce\\Catalog\\Controller, Markommerce\\Catalog\\Component, Markommerce\\Catalog\\Context, Markommerce\\Catalog\\Data, or Markommerce\\Catalog\\Iteration class exists after the move (grep the entire packages/ tree)',
    function (): void {
        $packagesDir = dirname(__DIR__, 3);

        $forbiddenNamespaces = [
            'Markommerce\\Catalog\\Controller',
            'Markommerce\\Catalog\\Component',
            'Markommerce\\Catalog\\Context',
            'Markommerce\\Catalog\\Data',
            'Markommerce\\Catalog\\Iteration',
        ];

        $violations = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packagesDir));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Skip vendor directories and test support files
            $path = $file->getPathname();
            if (str_contains($path, '/vendor/')) {
                continue;
            }

            $contents = file_get_contents($path);
            foreach ($forbiddenNamespaces as $ns) {
                // Check for namespace declaration (not just use statements in test files)
                if (str_contains($contents, "namespace $ns;")) {
                    $violations[] = "$path declares namespace $ns";
                }
            }
        }

        expect($violations)->toBeEmpty(implode("\n", $violations));
    },
);

it(
    'updates packages/catalog/tests/Unit/ScopeDecouplingTest.php to remove the two preservation blocks that target the now-moved CategoryControllerTest.php and CategoryLayoutTest.php (lines 96–116 of the existing file) so the catalog test suite stays green after the move',
    function (): void {
        $catalogTests = dirname(__DIR__, 3) . '/catalog/tests/Unit/ScopeDecouplingTest.php';
        $contents = file_get_contents($catalogTests);

        // The two removed blocks must be gone
        expect($contents)->not->toContain("'preserves all non-scope test cases in CategoryControllerTest");
        expect($contents)->not->toContain("'preserves all non-scope test cases in CategoryLayoutTest");

        // The remaining blocks must still be there
        expect($contents)->toContain("'preserves all non-scope test cases in CategoryTreeIntegrationTest");
        expect($contents)->toContain(
            "'preserves all non-scope test cases in CatalogSeederTreeTest and CatalogSeederTest",
        );
    },
);

it(
    'updates packages/catalog/resources/js/package.test.ts (after relocation to packages/catalog-storefront/resources/js/) so the describe label and any embedded literals reference the new @markommerce/catalog-storefront npm package name',
    function (): void {
        $storefrontRoot = dirname(__DIR__, 2);
        $testFile = $storefrontRoot . '/resources/js/package.test.ts';

        expect(file_exists($testFile))->toBeTrue();

        $contents = file_get_contents($testFile);
        expect($contents)->toContain('catalog-storefront package frontend wiring');
        expect($contents)->not->toContain("'catalog package frontend wiring'");
    },
);

it(
    'moves the catalog-scope ScopedProductGridComponent.php to catalog-storefront-scope with namespace Markommerce\\CatalogStorefrontScope\\Component (task 005)',
    function (): void {
        $catalogStorefrontScopeSrc = dirname(
            __DIR__,
            3,
        ) . '/catalog-storefront-scope/src/Component/ScopedProductGridComponent.php';

        expect(file_exists($catalogStorefrontScopeSrc))->toBeTrue();

        $contents = file_get_contents($catalogStorefrontScopeSrc);

        expect($contents)->toContain('use Markommerce\\CatalogStorefront\\Component\\ProductGridComponent;');
        expect($contents)->toContain('use Markommerce\\CatalogStorefront\\Data\\ProductGridData;');
        expect($contents)->toContain('namespace Markommerce\\CatalogStorefrontScope\\Component;');
        expect($contents)->not->toContain('namespace Markommerce\\CatalogScope\\Component;');

        // Must no longer exist in catalog-scope
        $catalogScopeSrc = dirname(__DIR__, 3) . '/catalog-scope/src/Component/ScopedProductGridComponent.php';
        expect(file_exists($catalogScopeSrc))->toBeFalse();
    },
);

it(
    'moves the catalog-scope ScopedProductGridComponentTest.php to catalog-storefront-scope with updated namespace (task 005)',
    function (): void {
        $testFile = dirname(
            __DIR__,
            3,
        ) . '/catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php';

        expect(file_exists($testFile))->toBeTrue();

        $contents = file_get_contents($testFile);

        expect($contents)->toContain('use Markommerce\\CatalogStorefront\\Component\\ProductGridComponent;');
        expect($contents)->toContain('Markommerce\\CatalogStorefrontScope\\Component\\ScopedProductGridComponent');
        expect($contents)->not->toContain('Markommerce\\CatalogScope\\Component\\ScopedProductGridComponent');

        // Must no longer exist in catalog-scope
        $oldTestFile = dirname(__DIR__, 3) . '/catalog-scope/tests/Unit/Component/ScopedProductGridComponentTest.php';
        expect(file_exists($oldTestFile))->toBeFalse();
    },
);

it(
    'removes markommerce/catalog-storefront from packages/catalog-scope/composer.json require after task 005 moved ScopedProductGridComponent out',
    function (): void {
        $catalogScopeComposer = dirname(__DIR__, 3) . '/catalog-scope/composer.json';

        expect(file_exists($catalogScopeComposer))->toBeTrue();

        $composer = json_decode(file_get_contents($catalogScopeComposer), true);

        expect($composer['require'])->not->toHaveKey('markommerce/catalog-storefront');
    },
);
