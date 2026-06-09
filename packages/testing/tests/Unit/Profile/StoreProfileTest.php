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
