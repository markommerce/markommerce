<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a Container pre-wired with scope module singletons and a config that
 * includes the market axis with both default and us scopes.
 */
function buildPriceAmountTestContainer(): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes' => [
                        'default' => [],
                        'us' => [],
                    ],
                ],
            ],
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);

    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    return $container;
}

/**
 * Build a ModuleManifest for the scope module.
 */
function priceAmountScopeManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 3) . '/scope/module.php';

    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
        boot: $module['boot'],
    );
}

/**
 * Build a ModuleManifest for the market module (provides market scope axis config).
 */
function priceAmountMarketManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the catalog module.
 */
function priceAmountCatalogManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the catalog-scope module.
 */
function priceAmountCatalogScopeManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog-scope',
        version: '1.0.0',
        require: [
            'markommerce/catalog' => '*',
            'markommerce/scope' => '*',
        ],
    );
}

/**
 * Build a ModuleManifest for the catalog-market bridge using its real module.php.
 */
function priceAmountCatalogMarketManifest(): ModuleManifest
{
    $bridgeModule = require dirname(__DIR__, 2) . '/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-market',
        version: '1.0.0',
        require: $bridgeModule['require'],
        boot: $bridgeModule['boot'],
    );
}

/**
 * Boot all modules in dependency order.
 *
 * @param ModuleManifest[] $ordered
 */
function runPriceAmountBootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

/**
 * Resolve all manifests via DependencyResolver and run boot.
 */
function bootPriceAmountModules(Container $container): void
{
    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        priceAmountScopeManifest(),
        priceAmountMarketManifest(),
        priceAmountCatalogManifest(),
        priceAmountCatalogScopeManifest(),
        priceAmountCatalogMarketManifest(),
    ]);

    runPriceAmountBootLoop($ordered, $container);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves a per market product price override when one is set', function (): void {
    DefaultScopeGuard::reset();

    $container = buildPriceAmountTestContainer();
    bootPriceAmountModules($container);

    $scopeResolver = $container->get(ScopeResolver::class);
    $scopeContext = $container->get(ScopeContext::class);

    $product = new Product();
    $product->priceAmount = '99.9900';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '79.9900');
    $product->attachCompanion($overrides);

    $scopeContext->clearAll();
    $scopeContext->in('market', 'us');

    $result = $scopeResolver->resolved($product, 'priceAmount');

    expect($result)->toBe('79.9900');
});

it('falls back to the global product price when no market override exists', function (): void {
    DefaultScopeGuard::reset();

    $container = buildPriceAmountTestContainer();
    bootPriceAmountModules($container);

    $scopeResolver = $container->get(ScopeResolver::class);
    $scopeContext = $container->get(ScopeContext::class);

    $product = new Product();
    $product->priceAmount = '99.9900';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('market:us', 'priceAmount', '79.9900');
    $product->attachCompanion($overrides);

    $scopeContext->clearAll();
    $scopeContext->in('market', 'default');

    $result = $scopeResolver->resolved($product, 'priceAmount');

    expect($result)->toBe('99.9900');
});
