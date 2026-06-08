<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Attributes\Preference;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Markommerce\CatalogPriceIndex\DefaultIndexedMarketsProvider;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogPriceIndexMarket\ScopedIndexedMarketsProvider;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function indexMarketBuildContainer(): Container
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
    $container->instance(ContainerInterface::class, $container);

    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';

    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($scopeModule['bindings'] as $interface => $binding) {
        $container->bind($interface, $binding);
    }

    return $container;
}

function indexMarketScopeManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 3) . '/scope/module.php';

    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
        boot: $module['boot'],
    );
}

function indexMarketMarketManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

function indexMarketCatalogPriceIndexManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/catalog-price-index',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

function indexMarketBridgeManifest(): ModuleManifest
{
    $module = require dirname(__DIR__, 2) . '/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-price-index-market',
        version: '1.0.0',
        require: $module['require'],
        boot: $module['boot'],
    );
}

function indexMarketBootModules(Container $container): void
{
    $resolver = new DependencyResolver();
    $ordered  = $resolver->resolve([
        indexMarketScopeManifest(),
        indexMarketMarketManifest(),
        indexMarketCatalogPriceIndexManifest(),
        indexMarketBridgeManifest(),
    ]);

    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers the index amount on the market axis', function (): void {
    DefaultScopeGuard::reset();

    $container = indexMarketBuildContainer();
    indexMarketBootModules($container);

    $registry = $container->get(ScopedFieldRegistry::class);

    expect($registry->axesForProperty(ProductPriceIndexEntry::class, 'amount'))->toContain('market');
});

it('resolves a per market index amount through the scope resolver', function (): void {
    DefaultScopeGuard::reset();

    $container = indexMarketBuildContainer();
    indexMarketBootModules($container);

    $scopeContext  = $container->get(ScopeContext::class);
    $scopeResolver = $container->get(ScopeResolver::class);

    $entry         = new ProductPriceIndexEntry();
    $entry->amount = '99.9900';
    $entry->setOverride('market:us', 'amount', '12.3400');

    $scopeContext->clearAll();
    $scopeContext->in('market', 'us');

    expect($scopeResolver->resolved($entry, 'amount'))->toBe('12.3400');
});

it('falls back to the base index amount when no market override exists', function (): void {
    DefaultScopeGuard::reset();

    $container = indexMarketBuildContainer();
    indexMarketBootModules($container);

    $scopeContext  = $container->get(ScopeContext::class);
    $scopeResolver = $container->get(ScopeResolver::class);

    $entry         = new ProductPriceIndexEntry();
    $entry->amount = '99.9900';

    $scopeContext->clearAll();
    $scopeContext->in('market', 'default');

    expect($scopeResolver->resolved($entry, 'amount'))->toBe('99.9900');
});

it('returns the configured markets excluding the default', function (): void {
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => [
                        'default' => [],
                        'us'      => [],
                        'eu'      => [],
                    ],
                ],
            ],
        ],
    ]);

    $registry = new PhpScopeRegistry($config);
    $provider = new ScopedIndexedMarketsProvider($registry);

    expect($provider->markets())->toBe(['us', 'eu']);
});

it('overrides the default markets provider via preference', function (): void {
    $reflection = new ReflectionClass(ScopedIndexedMarketsProvider::class);
    $attributes = $reflection->getAttributes(Preference::class);

    expect($attributes)->toHaveCount(1);
    expect($attributes[0]->newInstance()->replaces)->toBe(DefaultIndexedMarketsProvider::class);
});

it('returns no markets when the market axis is absent', function (): void {
    $config   = new ConfigRepository(['scope' => ['axes' => []]]);
    $registry = new PhpScopeRegistry($config);
    $provider = new ScopedIndexedMarketsProvider($registry);

    expect($provider->markets())->toBe([]);
});
