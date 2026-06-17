<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Tests\Support\FakeAttributeDefinitionRepository;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeScope\Entity\ProductScopedAttributeValues;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;

/**
 * Boots a fresh container using the catalog-attribute-scope module.php file.
 */
function bootCatalogAttributeScopeModuleContainer(): ContainerInterface
{
    $scopeRawConfig = require dirname(__DIR__, 3) . '/scope/config/scope.php';
    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';
    $attributeModule = require dirname(__DIR__, 3) . '/attribute/module.php';
    $catalogModule = require dirname(__DIR__, 3) . '/catalog/module.php';
    $catalogAttributeModule = require dirname(__DIR__, 3) . '/catalog-attribute/module.php';
    $catalogAttributeScopeModule = require dirname(__DIR__, 2) . '/module.php';

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(PreferenceRegistry::class, $preferenceRegistry);
    $container->instance(
        ConfigRepositoryInterface::class,
        new ConfigRepository(['scope' => $scopeRawConfig]),
    );

    // EntityMetadataFactory must be a singleton so boot modifications persist to later gets
    $container->singleton(EntityMetadataFactory::class);

    foreach ([$scopeModule, $attributeModule, $catalogModule, $catalogAttributeModule, $catalogAttributeScopeModule] as $module) {
        foreach ($module['bindings'] ?? [] as $interface => $implementation) {
            $container->bind($interface, $implementation);
        }
        foreach ($module['singletons'] ?? [] as $key => $value) {
            if (is_int($key)) {
                $container->singleton($value);
            } else {
                $container->bind($key, $value);
                $container->singleton($key);
            }
        }
        if (isset($module['boot']) && $module['boot'] instanceof Closure) {
            $container->call($module['boot']);
        }
    }

    $container->bind(
        AttributeDefinitionRepositoryInterface::class,
        FakeAttributeDefinitionRepository::class,
    );

    return $container;
}

// ─────────────────────────────────────────────────────────
// Requirements
// ─────────────────────────────────────────────────────────

it('links ProductScopedAttributeValues as an extender of Product after boot', function (): void {
    $container = bootCatalogAttributeScopeModuleContainer();

    $factory = $container->get(EntityMetadataFactory::class);
    $metadata = $factory->parse(Product::class);

    expect($metadata->extenders)->toContain(ProductScopedAttributeValues::class);
});

it('resolves the ScopedProductAttributeAccessor from the container', function (): void {
    $container = bootCatalogAttributeScopeModuleContainer();

    $accessor = $container->get(ScopedProductAttributeAccessor::class);

    expect($accessor)->toBeInstanceOf(ScopedProductAttributeAccessor::class);
});
