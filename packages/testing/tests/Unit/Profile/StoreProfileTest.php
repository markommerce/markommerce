<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
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
    expect($names)->toContain('markommerce/config-pgsql');
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
    \Markommerce\Testing\Database\TestConnection::skipIfUnavailable();
    \Markommerce\Scope\Storage\DefaultScopeGuard::reset();

    $envKey = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $envKey);

    try {
        $conn = new \Markommerce\Testing\Database\TestConnection();
        $profile = StoreProfile::storefront(storeProfileVendorDir());
        $store = $profile->boot($conn);

        $controller = $store->get(\Markommerce\CatalogStorefront\Controller\CategoryController::class);
        expect($controller)->toBeInstanceOf(\Markommerce\CatalogStorefront\Controller\CategoryController::class);
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
        \Markommerce\Scope\Storage\DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

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
