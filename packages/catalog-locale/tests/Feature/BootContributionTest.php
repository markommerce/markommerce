<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a real Container wired with the scope module's bindings/singletons,
 * with a config that defines the locale axis.
 */
function buildLocaleContainer(): Container
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'locale' => ['default' => 'default', 'scopes' => ['default' => []]],
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

    return $container;
}

/**
 * Build a ModuleManifest for the scope module.
 */
function localeTestScopeModuleManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
    );
}

/**
 * Build a ModuleManifest for the locale module.
 */
function localeModuleManifest(): ModuleManifest
{
    return new ModuleManifest(
        name: 'markommerce/locale',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );
}

/**
 * Build a ModuleManifest for the catalog-locale bridge module loaded from its real module.php.
 */
function catalogLocaleModuleManifest(): ModuleManifest
{
    $bridgeModule = require dirname(__DIR__, 2) . '/module.php';

    return new ModuleManifest(
        name: 'markommerce/catalog-locale',
        version: '1.0.0',
        require: $bridgeModule['require'] ?? ['markommerce/catalog-scope' => '*', 'markommerce/locale' => '*'],
        boot: $bridgeModule['boot'],
    );
}

/**
 * Run boot closures for an ordered list of manifests against a container.
 *
 * @param ModuleManifest[] $ordered
 */
function runCatalogLocaleBootLoop(array $ordered, ContainerInterface $container): void
{
    foreach ($ordered as $module) {
        if ($module->boot !== null) {
            $container->call($module->boot);
        }
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers Product.name as locale-scoped via ScopedFieldRegistry at boot', function (): void {
    $container = buildLocaleContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        localeTestScopeModuleManifest(),
        localeModuleManifest(),
        catalogLocaleModuleManifest(),
    ]);

    runCatalogLocaleBootLoop($ordered, $container);

    $registry = $container->get(ScopedFieldRegistry::class);
    expect($registry->axesForProperty(Product::class, 'name'))->toBe(['locale']);
});

it('registers Product.description as locale-scoped via ScopedFieldRegistry at boot', function (): void {
    $container = buildLocaleContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        localeTestScopeModuleManifest(),
        localeModuleManifest(),
        catalogLocaleModuleManifest(),
    ]);

    runCatalogLocaleBootLoop($ordered, $container);

    $registry = $container->get(ScopedFieldRegistry::class);
    expect($registry->axesForProperty(Product::class, 'description'))->toBe(['locale']);
});

it('registers Category.name as locale-scoped via ScopedFieldRegistry at boot', function (): void {
    $container = buildLocaleContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        localeTestScopeModuleManifest(),
        localeModuleManifest(),
        catalogLocaleModuleManifest(),
    ]);

    runCatalogLocaleBootLoop($ordered, $container);

    $registry = $container->get(ScopedFieldRegistry::class);
    expect($registry->axesForProperty(Category::class, 'name'))->toBe(['locale']);
});

it('registers Category.description as locale-scoped via ScopedFieldRegistry at boot', function (): void {
    $container = buildLocaleContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        localeTestScopeModuleManifest(),
        localeModuleManifest(),
        catalogLocaleModuleManifest(),
    ]);

    runCatalogLocaleBootLoop($ordered, $container);

    $registry = $container->get(ScopedFieldRegistry::class);
    expect($registry->axesForProperty(Category::class, 'description'))->toBe(['locale']);
});

it('does not register any field on entities other than Product and Category', function (): void {
    $container = buildLocaleContainer();

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        localeTestScopeModuleManifest(),
        localeModuleManifest(),
        catalogLocaleModuleManifest(),
    ]);

    runCatalogLocaleBootLoop($ordered, $container);

    $registry = $container->get(ScopedFieldRegistry::class);

    expect($registry->hasScopedProperties(Product::class))->toBeTrue()
        ->and($registry->hasScopedProperties(Category::class))->toBeTrue();

    // Only Product and Category should have scoped properties — nothing else
    $productProperties = $registry->propertiesFor(Product::class);
    $categoryProperties = $registry->propertiesFor(Category::class);

    expect(array_keys($productProperties))->toBe(['name', 'description'])
        ->and(array_keys($categoryProperties))->toBe(['name', 'description']);

    // A third-party entity class should have no scoped properties registered
    $otherEntity = CategoryTree::class;
    expect($registry->hasScopedProperties($otherEntity))->toBeFalse();
});

it('requires markommerce/catalog-scope and markommerce/locale in composer.json', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/catalog-scope')
        ->and($composer['require'])->toHaveKey('markommerce/locale');
});

it('declares itself as a marko-module via composer extra.marko.module=true', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('auto-injects ScopedFieldRegistry into the boot closure via container::call', function (): void {
    $container = buildLocaleContainer();

    $injectedRegistry = null;

    // Wrap the real boot closure to capture the injected ScopedFieldRegistry
    $bridgeModule = require dirname(__DIR__, 2) . '/module.php';
    $realBoot = $bridgeModule['boot'];

    $wrappedBoot = function (ScopedFieldRegistry $scopedFieldRegistry) use ($realBoot, &$injectedRegistry): void {
        $injectedRegistry = $scopedFieldRegistry;
        $realBoot($scopedFieldRegistry);
    };

    $bridge = new ModuleManifest(
        name: 'markommerce/catalog-locale',
        version: '1.0.0',
        require: ['markommerce/catalog-scope' => '*', 'markommerce/locale' => '*'],
        boot: $wrappedBoot,
    );

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve([
        localeTestScopeModuleManifest(),
        localeModuleManifest(),
        $bridge,
    ]);

    runCatalogLocaleBootLoop($ordered, $container);

    $expectedRegistry = $container->get(ScopedFieldRegistry::class);

    expect($injectedRegistry)->not->toBeNull()
        ->and($injectedRegistry)->toBe($expectedRegistry);
});

it(
    'boots after markommerce/scope and markommerce/locale so the locale axis is registered before the bridge runs',
    function (): void {
        $resolver = new DependencyResolver();
    
        // Pass catalog-locale first — DependencyResolver must reorder it after scope and locale
    $ordered = $resolver->resolve([
            catalogLocaleModuleManifest(),
            localeModuleManifest(),
            localeTestScopeModuleManifest(),
        ]);
    
        $names = array_map(fn (ModuleManifest $m) => $m->name, $ordered);
    
        $scopePosition = array_search('markommerce/scope', $names, true);
        $localePosition = array_search('markommerce/locale', $names, true);
        $bridgePosition = array_search('markommerce/catalog-locale', $names, true);
    
        expect($scopePosition)->toBeInt()
            ->and($localePosition)->toBeInt()
            ->and($bridgePosition)->toBeInt();
    
        /** @var int $scopePosition */
        /** @var int $localePosition */
        /** @var int $bridgePosition */
        expect($scopePosition)->toBeLessThan($bridgePosition)
            ->and($localePosition)->toBeLessThan($bridgePosition);
    }
);
