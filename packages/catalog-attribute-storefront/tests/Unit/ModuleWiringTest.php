<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Catalog\Filtering\ProductListFilterRegistry;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeIndex\Query\AttributeExistsClause;
use Markommerce\CatalogAttributeIndex\Tests\Support\QueryableAttributeDefinitionRepository;
use Markommerce\CatalogAttributeStorefront\Filter\AttributeProductListFilter;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @return array<string, mixed>
 */
function loadCatalogModule(): array
{
    /** @var array<string, mixed> */
    return require dirname(__DIR__, 3) . '/catalog/module.php';
}

/**
 * @return array<string, mixed>
 */
function loadStorefrontModule(): array
{
    /** @var array<string, mixed> */
    return require dirname(__DIR__, 2) . '/module.php';
}

/**
 * Build a minimal container with the catalog and catalog-attribute-storefront modules applied,
 * plus stubs for deps that cannot be auto-wired in a unit-test context.
 */
function buildStorefrontModuleContainer(): Container
{
    $catalogModule    = loadCatalogModule();
    $storefrontModule = loadStorefrontModule();

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);

    // Provide a no-op ScopeRegistryInterface so SignatureCandidateEnumerator + ScopeContext
    // can be auto-wired without pulling in the full scope module.
    $container->instance(
        ScopeRegistryInterface::class,
        new class implements ScopeRegistryInterface {
            public function hasAxis(string $name): bool
            {
                return false;
            }

            public function getAxis(string $name): ScopeAxis
            {
                throw new \RuntimeException('No axes in stub registry');
            }

            /** @return list<string> */
            public function listAxes(): array
            {
                return [];
            }

            public function getHierarchy(string $axisName): ScopeHierarchy
            {
                throw new \RuntimeException('No axes in stub registry');
            }
        },
    );

    // Provide an in-memory AttributeDefinitionRepositoryInterface.
    $container->instance(
        AttributeDefinitionRepositoryInterface::class,
        new QueryableAttributeDefinitionRepository(),
    );

    foreach ($catalogModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($catalogModule['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($storefrontModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($storefrontModule['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    return $container;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('binds the layered-navigation assembler', function (): void {
    $module = loadStorefrontModule();

    expect($module['bindings'] ?? [])->toHaveKey(LayeredNavigationAssembler::class);
});

it('registers the ProductListFilterRegistry singleton in the catalog module', function (): void {
    $module = loadCatalogModule();

    expect($module['singletons'] ?? [])->toContain(ProductListFilterRegistry::class);
});

it('binds the AttributeFacetQuery service', function (): void {
    $module = require dirname(__DIR__, 3) . '/catalog-attribute-index/module.php';

    expect($module['bindings'] ?? [])->toHaveKey(AttributeFacetQuery::class);
});

it('registers the AttributeProductListFilter into the registry after boot', function (): void {
    $storefrontModule = loadStorefrontModule();
    $container        = buildStorefrontModuleContainer();

    $container->call($storefrontModule['boot']);

    $registry = $container->get(ProductListFilterRegistry::class);
    $filters  = $registry->all();

    expect($filters)->not->toBeEmpty()
        ->and($filters[0])->toBeInstanceOf(AttributeProductListFilter::class);
});
