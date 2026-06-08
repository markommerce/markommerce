<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Catalog\Config\CatalogPaginationConfig;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

it('defaults the page size to 24 and registers the catalog/pagination key', function (): void {
    $config = new CatalogPaginationConfig();

    expect($config->defaultPageSize)->toBe(24);

    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CatalogPaginationConfig::class]);

    $definition = $registry->definition(CatalogPaginationConfig::class, 'defaultPageSize');

    expect($definition->key)->toBe('catalog/pagination.defaultPageSize')
        ->and($definition->defaultValue)->toBe(24);
});

it('defaults to the offset strategy and numbered presentation', function (): void {
    $config = new CatalogPaginationConfig();

    expect($config->strategy)->toBe('offset')
        ->and($config->presentation)->toBe('numbered');

    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CatalogPaginationConfig::class]);

    $strategyDef = $registry->definition(CatalogPaginationConfig::class, 'strategy');
    $presentationDef = $registry->definition(CatalogPaginationConfig::class, 'presentation');

    expect($strategyDef->key)->toBe('catalog/pagination.strategy')
        ->and($strategyDef->defaultValue)->toBe('offset')
        ->and($presentationDef->key)->toBe('catalog/pagination.presentation')
        ->and($presentationDef->defaultValue)->toBe('numbered');
});

it('registers the allowed page sizes as an array definition', function (): void {
    $config = new CatalogPaginationConfig();

    expect($config->allowedPageSizes)->toBe([12, 24, 48, 96]);

    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CatalogPaginationConfig::class]);

    $definition = $registry->definition(CatalogPaginationConfig::class, 'allowedPageSizes');

    expect($definition->key)->toBe('catalog/pagination.allowedPageSizes')
        ->and($definition->type)->toBe('array')
        ->and($definition->defaultValue)->toBe([12, 24, 48, 96]);
});

it('defaults the sort to position and limits allowed sorts to real columns', function (): void {
    $config = new CatalogPaginationConfig();

    expect($config->defaultSort)->toBe('position')
        ->and($config->allowedSorts)->toBe(['position', 'name', 'sku', 'price'])
        ->and($config->allowedSorts)->not->toContain('created_at');

    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CatalogPaginationConfig::class]);

    $defaultSortDef = $registry->definition(CatalogPaginationConfig::class, 'defaultSort');
    $allowedSortsDef = $registry->definition(CatalogPaginationConfig::class, 'allowedSorts');

    expect($defaultSortDef->key)->toBe('catalog/pagination.defaultSort')
        ->and($defaultSortDef->defaultValue)->toBe('position')
        ->and($allowedSortsDef->key)->toBe('catalog/pagination.allowedSorts')
        ->and($allowedSortsDef->type)->toBe('array')
        ->and($allowedSortsDef->defaultValue)->toBe(['position', 'name', 'sku', 'price']);
});

it('registers the max page depth default', function (): void {
    $config = new CatalogPaginationConfig();

    expect($config->maxPageDepth)->toBe(100);

    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CatalogPaginationConfig::class]);

    $definition = $registry->definition(CatalogPaginationConfig::class, 'maxPageDepth');

    expect($definition->key)->toBe('catalog/pagination.maxPageDepth')
        ->and($definition->type)->toBe('int')
        ->and($definition->defaultValue)->toBe(100);
});

it('applies a per-market scope override to a scoped pagination field via an in-memory scoped resolver', function (): void {
    // Build a minimal in-memory scope registry with a 'market' axis
    $scopeRegistry = new class (['market' => ['global', 'de', 'fr']]) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap */
        public function __construct(array $axesMap)
        {
            $this->builtAxes = [];
            foreach ($axesMap as $name => $paths) {
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $paths[0]);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        public function getAxis(string $name): ScopeAxis
        {
            if (!isset($this->builtAxes[$name])) {
                throw UnknownAxisException::forAxis($name);
            }

            return $this->builtAxes[$name];
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };

    $builder = new ConfigRegistryBuilder();
    $configRegistry = $builder->build([CatalogPaginationConfig::class]);

    $globalStorage = new InMemoryConfigStorage();
    $scopedStorage = new InMemoryScopedConfigStorage();

    // Store a per-market override: defaultPageSize = 48 for market 'de'
    $scopedStorage->saveOverride('catalog/pagination.defaultPageSize', 'market:de', 48);

    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $scopeContext = new ScopeContext($scopeRegistry);

    $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);
    $scopedFieldRegistry->register(CatalogPaginationConfig::class, 'defaultPageSize', ['market']);

    $resolver = new ScopedConfigResolver(
        configRegistry: $configRegistry,
        configStorage: $globalStorage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
        scopedConfigStorage: $scopedStorage,
        overrideMatcher: $overrideMatcher,
        scopeContext: $scopeContext,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    // Without market scope: falls back to default (24)
    $result = $resolver->resolved(CatalogPaginationConfig::class, 'defaultPageSize');
    expect($result)->toBe(24);

    // With market 'de' scope: returns override (48)
    $scopeContext->in('market', 'de');
    $result = $resolver->resolved(CatalogPaginationConfig::class, 'defaultPageSize');
    expect($result)->toBe(48);
});
