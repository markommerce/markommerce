<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Profile\StoreProfile;

function storeProfileVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

it('resolves the module set for the simple profile', function (): void {
    $profile = StoreProfile::simple(storeProfileVendorDir());

    $modules = $profile->modules();

    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    expect($names)->toContain('markommerce/catalog');
    expect($names)->toContain('marko/core');
    expect($names)->toContain('markommerce/config');
    // simple profile should NOT include market or locale
    expect($names)->not->toContain('markommerce/market');
    expect($names)->not->toContain('markommerce/locale');
});

it('resolves the storefront module set including marko routing view-latte and vite', function (): void {
    $profile = StoreProfile::storefront(storeProfileVendorDir());

    $modules = $profile->modules();
    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    // marko rendering stack
    expect($names)->toContain('marko/routing');
    expect($names)->toContain('marko/view');
    expect($names)->toContain('marko/view-latte');
    expect($names)->toContain('marko/vite');

    // markommerce storefront stack
    expect($names)->toContain('markommerce/catalog');
    expect($names)->toContain('markommerce/catalog-storefront');
    expect($names)->toContain('markommerce/catalog-price-index');
    expect($names)->toContain('markommerce/config');
    expect($names)->not->toContain('markommerce/config-pgsql');
    expect($names)->toContain('markommerce/layout');
    expect($names)->toContain('markommerce/frontend');
    expect($names)->toContain('markommerce/theme-blank');
});

it('excludes scope locale and market modules from the storefront profile', function (): void {
    $profile = StoreProfile::storefront(storeProfileVendorDir());

    $modules = $profile->modules();
    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    // non-scoped storefront: no market/catalog-scope/catalog-storefront-scope
    expect($names)->not->toContain('markommerce/market');
    expect($names)->not->toContain('markommerce/catalog-scope');
    expect($names)->not->toContain('markommerce/catalog-storefront-scope');
    expect($names)->not->toContain('markommerce/catalog-market');
    expect($names)->not->toContain('markommerce/catalog-locale');
    expect($names)->not->toContain('markommerce/catalog-price-index-market');
});

it('boots the storefront profile against a connection and resolves a storefront service', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $envKey = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $envKey);

    try {
        $conn = new TestConnection();
        $profile = StoreProfile::storefront(storeProfileVendorDir());
        $store = $profile->boot($conn);

        $controller = $store->get(CategoryController::class);
        expect($controller)->toBeInstanceOf(CategoryController::class);
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('lists markommerce/config (not config-pgsql) in the StoreProfile storefront preset', function (): void {
    $profile = StoreProfile::storefront(storeProfileVendorDir());

    $modules = $profile->modules();
    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    expect($names)->toContain('markommerce/config');
    expect($names)->not->toContain('markommerce/config-pgsql');
});

it(
    'resolves storage/renderer/repository interfaces from a profile listing only the parent packages',
    function (): void {
        // Verify that all StoreProfile presets resolve their module sets entirely from parent packages
    // (the retired -pgsql driver packages must NOT appear), while marko/database-pgsql (the
    // framework-level driver) is still present in any profile that needs database access.
    $retiredDrivers = [
            'markommerce/scope-pgsql',
            'markommerce/config-pgsql',
            'markommerce/config-scope-pgsql',
            'markommerce/attribute-pgsql',
        ];
    
        $profiles = [
            StoreProfile::simple(storeProfileVendorDir()),
            StoreProfile::storefront(storeProfileVendorDir()),
            StoreProfile::twoMarketsTwoLocales(storeProfileVendorDir()),
            StoreProfile::singleMarketTwoLocales(storeProfileVendorDir()),
        ];
    
        foreach ($profiles as $profile) {
            $names = array_map(fn (ModuleManifest $m) => $m->name, $profile->modules());
    
            foreach ($retiredDrivers as $driver) {
                expect($names)->not->toContain($driver);
            }
        }
    
        // marko/database-pgsql is the framework driver — must still be present in the simple preset
    $simpleNames = array_map(
        fn (ModuleManifest $m) => $m->name,
        StoreProfile::simple(storeProfileVendorDir())->modules()
    );
        expect($simpleNames)->toContain('marko/database-pgsql');
    }
);

it('has no markommerce/*-pgsql consumer reference in StoreProfile presets or comments', function (): void {
    $sourceFile = dirname(__DIR__, 3) . '/src/Profile/StoreProfile.php';
    $content = (string) file_get_contents($sourceFile);

    // marko/database-pgsql is the framework driver (KEEP); markommerce/*-pgsql are the retired drivers (MUST be absent)
    expect($content)->not->toContain('markommerce/scope-pgsql');
    expect($content)->not->toContain('markommerce/config-pgsql');
    expect($content)->not->toContain('markommerce/config-scope-pgsql');
    expect($content)->not->toContain('markommerce/attribute-pgsql');
});

it(
    'finds zero REAL markommerce/*-pgsql references across packages and root composer (intentional absence-assertions excluded)',
    function (): void {
        // Check the high-signal files where a real consumer reference would live:
    // - packages/*/composer.json (require/repositories/autoload entries)
    // - packages/*/README.md (install instructions)
    // - packages/*/module.php (module definitions)
    // - packages/testing/src/**/*.php (StoreProfile presets, module resolver lists)
    // - root composer.json (repositories/require/autoload-dev)
    //
    // We do NOT scan test files — they intentionally contain absence-assertions
    // (->not->toContain, ->not->toHaveKey, etc.) to guard against re-introduction.

        $repoRoot = dirname(__DIR__, 7); // packages/testing/tests/Unit/Profile -> repo root
    $retiredDrivers = [
            'markommerce/scope-pgsql',
            'markommerce/config-pgsql',
            'markommerce/config-scope-pgsql',
            'markommerce/attribute-pgsql',
        ];
    
        // Files to scan for real references
    $filesToScan = [];
    
        // Root composer.json
    $rootComposer = $repoRoot . '/composer.json';
    
        if (file_exists($rootComposer)) {
            $filesToScan[] = $rootComposer;
        }
    
        // packages/*/composer.json, README.md, module.php
    $packagesDir = $repoRoot . '/packages';
        $packageDirs = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];
    
        foreach ($packageDirs as $packageDir) {
            foreach (['composer.json', 'README.md', 'module.php'] as $filename) {
                $file = $packageDir . '/' . $filename;
    
                if (file_exists($file)) {
                    $filesToScan[] = $file;
                }
            }
        }
    
        // packages/testing/src/**/*.php (StoreProfile and related source)
    $testingSrcDir = $repoRoot . '/packages/testing/src';
    
        if (is_dir($testingSrcDir)) {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($testingSrcDir, FilesystemIterator::SKIP_DOTS),
            );
    
            /** @var SplFileInfo $file */
            foreach ($iter as $file) {
                if ($file->getExtension() === 'php') {
                    $filesToScan[] = $file->getPathname();
                }
            }
        }
    
        $violations = [];
    
        foreach ($filesToScan as $filePath) {
            $content = (string) file_get_contents($filePath);
    
            foreach ($retiredDrivers as $driver) {
                if (str_contains($content, $driver)) {
                    $violations[] = str_replace($repoRoot . '/', '', $filePath) . ' contains ' . $driver;
                }
            }
        }
    
        expect($violations)->toBe([]);
    }
);

it('resolves market and locale modules for the two-markets profile', function (): void {
    $profile = StoreProfile::twoMarketsTwoLocales(storeProfileVendorDir());

    $modules = $profile->modules();
    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    expect($names)->toContain('markommerce/catalog');
    expect($names)->toContain('markommerce/market');
    expect($names)->toContain('markommerce/locale');
    expect($names)->toContain('markommerce/catalog-market');
    expect($names)->toContain('markommerce/catalog-price-index-market');

    // Check axes and values
    expect($profile->declaredAxes())->toContain('market');
    expect($profile->declaredAxes())->toContain('locale');
    expect($profile->markets())->toBe(['us', 'eu']);
});
