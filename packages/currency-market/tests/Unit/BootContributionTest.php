<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Signature\ScopeSignature;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a Container wired with scope module bindings and a market axis config.
 *
 * Includes both 'default' and 'us' scopes so ScopeContext::in('market', 'us') succeeds.
 */
function buildCurrencyMarketContainer(): Container
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
function currencyMarketScopeManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the market module.
 */
function currencyMarketMarketManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the currency-market bridge loaded from its real module.php.
 */
function currencyMarketBridgeManifest(): ModuleManifest
{
    $bridgeModule = require dirname(__DIR__, 2) . '/module.php';

    return new ModuleManifest(
        name: 'markommerce/currency-market',
        version: '1.0.0',
        require: $bridgeModule['require'],
        boot: $bridgeModule['boot'],
    );
}

/**
 * Run boot closures for an ordered list of manifests against a container.
 *
 * @param array<ModuleManifest> $ordered
 */
function runCurrencyMarketBootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers the currency base config key on the market axis', function (): void {
    $container = buildCurrencyMarketContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        currencyMarketScopeManifest(),
        currencyMarketMarketManifest(),
        currencyMarketBridgeManifest(),
    ]);

    runCurrencyMarketBootLoop($ordered, $container);

    $registry = $container->get(ScopedFieldRegistry::class);
    expect($registry->axesForProperty(CurrencyConfig::class, 'base'))->toBe(['market']);
});

it('resolves a per market base currency override when one is set', function (): void {
    $container = buildCurrencyMarketContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        currencyMarketScopeManifest(),
        currencyMarketMarketManifest(),
        currencyMarketBridgeManifest(),
    ]);

    runCurrencyMarketBootLoop($ordered, $container);

    // Store a per-market override: market=us => base = 'EUR'
    $storage = $container->get(ScopedConfigStorageInterface::class);
    $signature = new ScopeSignature(['market' => 'us']);
    $storage->saveOverride('currency/base', $signature->toString(), 'EUR');

    // Activate the us market scope
    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->in('market', 'us');

    $registry = $container->get(ScopedFieldRegistry::class);
    $overrides = $storage->loadOverrides('currency/base');

    $overrideMatcher = $container->get(OverrideMatcher::class);
    $axes = $registry->axesForProperty(CurrencyConfig::class, 'base');
    $matched = $overrideMatcher->match($overrides, $axes, $scopeContext);

    expect($matched)->toBe('EUR');
});

it('falls back to the global base currency when no market override exists', function (): void {
    $container = buildCurrencyMarketContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        currencyMarketScopeManifest(),
        currencyMarketMarketManifest(),
        currencyMarketBridgeManifest(),
    ]);

    runCurrencyMarketBootLoop($ordered, $container);

    $storage = $container->get(ScopedConfigStorageInterface::class);
    $scopeContext = $container->get(ScopeContext::class);
    $scopeContext->in('market', 'us');

    $registry = $container->get(ScopedFieldRegistry::class);
    $overrides = $storage->loadOverrides('currency/base');

    $overrideMatcher = $container->get(OverrideMatcher::class);
    $axes = $registry->axesForProperty(CurrencyConfig::class, 'base');
    $matched = $overrideMatcher->match($overrides, $axes, $scopeContext);

    // No override stored — should return null (caller falls back to global)
    expect($matched)->toBeNull();
});
