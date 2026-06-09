<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Profile\Exceptions\MissingAppConfigPathException;
use Markommerce\Testing\Profile\Exceptions\UndeclaredAxisException;
use Markommerce\Testing\Profile\StoreProfile;

function bootedStoreVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

it('injects market axis values into the scope config', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $conn = new TestConnection();
    $profile = StoreProfile::twoMarketsTwoLocales(bootedStoreVendorDir());
    $store = $profile->boot($conn);

    try {
        $registry = $store->get(ScopeRegistryInterface::class);

        expect($registry->hasAxis('market'))->toBeTrue();
        expect($registry->getHierarchy('market')->exists('us'))->toBeTrue();
        expect($registry->getHierarchy('market')->exists('eu'))->toBeTrue();
    } finally {
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('injects locale axis values for a single-market-two-locale profile', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $conn = new TestConnection();
    $profile = StoreProfile::singleMarketTwoLocales(bootedStoreVendorDir());
    $store = $profile->boot($conn);

    try {
        $registry = $store->get(ScopeRegistryInterface::class);

        expect($registry->hasAxis('locale'))->toBeTrue();
        expect($registry->getHierarchy('locale')->exists('en'))->toBeTrue();
        expect($registry->getHierarchy('locale')->exists('de'))->toBeTrue();
    } finally {
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('boots a container whose repositories use the supplied connection', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $conn = new TestConnection();
    $profile = StoreProfile::simple(bootedStoreVendorDir());
    $store = $profile->boot($conn);

    try {
        // The connection bound in the container must be the exact instance passed to boot()
        $resolvedConn = $store->get(ConnectionInterface::class);
        expect($resolvedConn)->toBe($conn);
    } finally {
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('runs a closure within an active market and locale scope', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $conn = new TestConnection();
    $profile = StoreProfile::twoMarketsTwoLocales(bootedStoreVendorDir());
    $store = $profile->boot($conn);

    try {
        $capturedMarket = null;
        $capturedLocale = null;

        $store->inScope('us', 'en', function () use ($store, &$capturedMarket, &$capturedLocale): void {
            $scopeContext = $store->get(ScopeContext::class);
            $capturedMarket = $scopeContext->get('market');
            $capturedLocale = $scopeContext->get('locale');
        });

        expect($capturedMarket)->toBe('us');
        expect($capturedLocale)->toBe('en');

        // Scope must be cleared after fn runs
        $scopeContext = $store->get(ScopeContext::class);
        expect($scopeContext->get('market'))->toBeNull();
        expect($scopeContext->get('locale'))->toBeNull();
    } finally {
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('throws when inScope is given an axis the profile does not declare', function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();

    $conn = new TestConnection();
    // simple profile has no market or locale axes
    $profile = StoreProfile::simple(bootedStoreVendorDir());
    $store = $profile->boot($conn);

    try {
        expect(fn () => $store->inScope('us', null, fn () => null))
            ->toThrow(UndeclaredAxisException::class);
    } finally {
        DefaultScopeGuard::reset();
    }
})->group('integration-destructive');

it('requires an explicit app config path for fromInstalled', function (): void {
    $vendorDir = bootedStoreVendorDir();

    // Ensure env var is not set
    putenv('MARKO_APP_CONFIG_PATH');

    expect(fn () => StoreProfile::fromInstalled($vendorDir))
        ->toThrow(MissingAppConfigPathException::class);
});

it('exposes the entity directories for the booted module set', function (): void {
    $profile = StoreProfile::simple(bootedStoreVendorDir());

    $entityDirs = $profile->entityDirs();

    expect($entityDirs)->not->toBeEmpty();

    // Must contain catalog entity dir
    $hasCatalog = array_any(
        $entityDirs,
        fn (string $dir) => str_contains($dir, 'catalog') && str_contains($dir, 'Entity'),
    );
    expect($hasCatalog)->toBeTrue();

    // All dirs must exist
    foreach ($entityDirs as $dir) {
        expect(is_dir($dir))->toBeTrue("Entity dir does not exist: $dir");
    }
});
