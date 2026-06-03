<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Tax\Config\TaxConfig;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a Container wired with scope module bindings and a market axis config.
 *
 * Includes both 'default' and 'us' scopes so ScopeContext::in('market', 'us') succeeds.
 */
function buildTaxMarketContainer(): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => [
                        'default' => [],
                        'us'      => [],
                    ],
                ],
            ],
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // Bind InMemoryScopedConfigStorage for the scoped config writer/resolver
    $container->bind(ScopedConfigStorageInterface::class, InMemoryScopedConfigStorage::class);

    return $container;
}

/**
 * Build a ModuleManifest for the scope module (no boot needed for axis registration).
 */
function taxMarketScopeManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the market module.
 */
function taxMarketMarketManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the tax-market bridge loaded from its real module.php.
 */
function taxMarketBridgeManifest(): ModuleManifest
{
    $bridgeModule = require dirname(__DIR__, 2) . '/module.php';

    return new ModuleManifest(
        name: 'markommerce/tax-market',
        version: '1.0.0',
        require: $bridgeModule['require'],
        boot: $bridgeModule['boot'],
    );
}

/**
 * Run boot closures for an ordered list of manifests against a container.
 *
 * @param list<ModuleManifest> $ordered
 */
function runTaxMarketBootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers the tax mode config key on the market axis', function (): void {
    $container = buildTaxMarketContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        taxMarketScopeManifest(),
        taxMarketMarketManifest(),
        taxMarketBridgeManifest(),
    ]);

    runTaxMarketBootLoop($ordered, $container);

    $registry = $container->get(ScopedFieldRegistry::class);
    expect($registry->axesForProperty(TaxConfig::class, 'pricesIncludeTax'))->toBe(['market']);
});

it('resolves a per market tax mode override when one is set', function (): void {
    $container = buildTaxMarketContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        taxMarketScopeManifest(),
        taxMarketMarketManifest(),
        taxMarketBridgeManifest(),
    ]);

    runTaxMarketBootLoop($ordered, $container);

    // Store a per-market override: market=us => pricesIncludeTax = true
    $storage = $container->get(ScopedConfigStorageInterface::class);
    $signature = new ScopeSignature(['market' => 'us']);
    $storage->saveOverride('tax/prices_include_tax', $signature->toString(), true);

    // Activate the us market scope
    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->in('market', 'us');

    $registry = $container->get(ScopedFieldRegistry::class);
    $overrides = $storage->loadOverrides('tax/prices_include_tax');

    $overrideMatcher = $container->get(\Markommerce\ConfigScope\Resolution\OverrideMatcher::class);
    $axes = $registry->axesForProperty(TaxConfig::class, 'pricesIncludeTax');
    $matched = $overrideMatcher->match($overrides, $axes, $scopeContext);

    expect($matched)->toBeTrue();
});

it('falls back to the global tax mode when no market override exists', function (): void {
    $container = buildTaxMarketContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        taxMarketScopeManifest(),
        taxMarketMarketManifest(),
        taxMarketBridgeManifest(),
    ]);

    runTaxMarketBootLoop($ordered, $container);

    $storage = $container->get(ScopedConfigStorageInterface::class);
    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->in('market', 'us');

    $registry = $container->get(ScopedFieldRegistry::class);
    $overrides = $storage->loadOverrides('tax/prices_include_tax');

    $overrideMatcher = $container->get(\Markommerce\ConfigScope\Resolution\OverrideMatcher::class);
    $axes = $registry->axesForProperty(TaxConfig::class, 'pricesIncludeTax');
    $matched = $overrideMatcher->match($overrides, $axes, $scopeContext);

    // No override stored — should return null (caller falls back to global)
    expect($matched)->toBeNull();
});
