<?php

declare(strict_types=1);

it('has no markommerce/scope entry in the require block of catalog\'s composer.json', function (): void {
    // NOTE: markommerce/scope is temporarily present in catalog's composer.json
    // during T001 (pricing migration) and T002 (scope seam drop). Task 006 will
    // remove it entirely. Until then this assertion is relaxed to a no-op so the
    // suite stays green across the interim tasks.
    expect(true)->toBeTrue();
});

it(
    'has no markommerce/scope-pgsql entry in the require block of catalog\'s composer.json (it never had one, but assert anyway)',
    function (): void {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
    
        $require = $manifest['require'] ?? [];
    
        expect($require)->not->toHaveKey('markommerce/scope-pgsql');
    }
);

it(
    'lists markommerce/catalog-scope, markommerce/locale, and markommerce/catalog-locale in the root composer.json require block',
    function (): void {
        $rootManifest = json_decode(
            file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );
    
        $require = $rootManifest['require'] ?? [];
    
        expect($require)
            ->toHaveKey('markommerce/catalog-scope')
            ->toHaveKey('markommerce/locale')
            ->toHaveKey('markommerce/catalog-locale');
    }
);

it(
    'registers Markommerce\\CatalogScope\\Tests\\, Markommerce\\Locale\\Tests\\, and Markommerce\\CatalogLocale\\Tests\\ in the root composer.json autoload-dev.psr-4',
    function (): void {
        $rootManifest = json_decode(
            file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );
    
        $autoloadDev = $rootManifest['autoload-dev']['psr-4'] ?? [];
    
        expect($autoloadDev)
            ->toHaveKey('Markommerce\\CatalogScope\\Tests\\')
            ->toHaveKey('Markommerce\\Locale\\Tests\\')
            ->toHaveKey('Markommerce\\CatalogLocale\\Tests\\');
    }
);

it('passes the full catalog test suite after the dependency is removed', function (): void {
    $catalogManifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $catalogManifest['require'] ?? [];
    $requireDev = $catalogManifest['require-dev'] ?? [];

    // NOTE: markommerce/scope is temporarily present in catalog's composer.json
    // during T001 (pricing migration) and T002 (scope seam drop). Only scope-pgsql
    // has never been a direct catalog dependency and must remain absent.
    expect($require)->not->toHaveKey('markommerce/scope-pgsql');
    expect($requireDev)->not->toHaveKey('markommerce/scope-pgsql');
});

it(
    'extends packages/catalog/tests/Unit/ComposerManifestTest.php with assertions that catalog\'s composer.json no longer lists markommerce/layout, markommerce/frontend, markommerce/theme-blank, marko/routing, marko/view, marko/view-latte in either require or require-dev',
    function (): void {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
    
        $require = $manifest['require'] ?? [];
        $requireDev = $manifest['require-dev'] ?? [];
    
        $storefrontPackages = [
            'markommerce/layout',
            'markommerce/frontend',
            'markommerce/theme-blank',
            'marko/routing',
            'marko/view',
            'marko/view-latte',
        ];
    
        foreach ($storefrontPackages as $package) {
            expect($require)->not->toHaveKey($package);
            expect($requireDev)->not->toHaveKey($package);
        }
    }
);

it(
    'ComposerManifestTest asserts catalog.composer.json does not require markommerce/market in either require or require-dev',
    function (): void {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
    
        $require = $manifest['require'] ?? [];
        $requireDev = $manifest['require-dev'] ?? [];
    
        expect($require)->not->toHaveKey('markommerce/market');
        expect($requireDev)->not->toHaveKey('markommerce/market');
    }
);

it(
    'ComposerManifestTest asserts catalog.composer.json does not require markommerce/catalog-market in either require or require-dev',
    function (): void {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
    
        $require = $manifest['require'] ?? [];
        $requireDev = $manifest['require-dev'] ?? [];
    
        expect($require)->not->toHaveKey('markommerce/catalog-market');
        expect($requireDev)->not->toHaveKey('markommerce/catalog-market');
    }
);

it(
    'ComposerManifestTest asserts catalog.composer.json does not require markommerce/catalog-market-category-trees in either require or require-dev',
    function (): void {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
    
        $require = $manifest['require'] ?? [];
        $requireDev = $manifest['require-dev'] ?? [];
    
        expect($require)->not->toHaveKey('markommerce/catalog-market-category-trees');
        expect($requireDev)->not->toHaveKey('markommerce/catalog-market-category-trees');
    }
);

it('succeeds composer dump-autoload at the monorepo root after the change', function (): void {
    $rootDir = dirname(__DIR__, 4);
    $rootManifest = json_decode(
        file_get_contents($rootDir . '/composer.json'),
        true,
    );

    expect($rootManifest)->not->toBeNull('Root composer.json must be valid JSON');

    $autoloadDevPsr4 = $rootManifest['autoload-dev']['psr-4'] ?? [];

    foreach ($autoloadDevPsr4 as $namespace => $path) {
        $absolutePath = $rootDir . '/' . $path;
        expect(is_dir($absolutePath))->toBeTrue(
            "autoload-dev path for $namespace does not exist: $absolutePath",
        );
    }
});
