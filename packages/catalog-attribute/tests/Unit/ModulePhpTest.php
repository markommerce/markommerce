<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Contracts\AttributeValueAccessorInterface;
use Markommerce\Attribute\Exceptions\DuplicateEntityClassRegistrationException;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Entity\ProductAttributeValues;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttribute\Tests\Support\FakeAttributeDefinitionRepository;

/**
 * Boots a fresh container using the catalog-attribute module.php file.
 */
function bootCatalogAttributeModuleContainer(): ContainerInterface
{
    // First boot the attribute module (dependency)
    $attributeModule = require dirname(__DIR__, 3) . '/attribute/module.php';
    $catalogAttributeModule = require dirname(__DIR__, 2) . '/module.php';

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(PreferenceRegistry::class, $preferenceRegistry);

    // Apply attribute module
    foreach ($attributeModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }
    foreach ($attributeModule['singletons'] ?? [] as $key => $value) {
        if (is_int($key)) {
            $container->singleton($value);
        } else {
            $container->bind($key, $value);
            $container->singleton($key);
        }
    }
    if (isset($attributeModule['boot']) && $attributeModule['boot'] instanceof Closure) {
        $container->call($attributeModule['boot']);
    }

    // Apply catalog-attribute module
    foreach ($catalogAttributeModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }
    foreach ($catalogAttributeModule['singletons'] ?? [] as $key => $value) {
        if (is_int($key)) {
            $container->singleton($value);
        } else {
            $container->bind($key, $value);
            $container->singleton($key);
        }
    }
    if (isset($catalogAttributeModule['boot']) && $catalogAttributeModule['boot'] instanceof Closure) {
        $container->call($catalogAttributeModule['boot']);
    }

    return $container;
}

// ─────────────────────────────────────────────────────────
// Requirements
// ─────────────────────────────────────────────────────────

it(
    'auto-discovers ProductAttributeValues as a Product extender via linkExtendersFrom on the discovered entity list',
    function (): void {
        $factory = new EntityMetadataFactory();
        $factory->linkExtendersFrom([
            Product::class,
            ProductAttributeValues::class,
        ]);
    
        $metadata = $factory->parse(Product::class);
    
        expect($metadata->extenders)->toContain(ProductAttributeValues::class);
    }
);

it('binds AttributeValueAccessorInterface to ProductAttributeAccessor', function (): void {
    $container = bootCatalogAttributeModuleContainer();

    // ProductAttributeAccessor depends on AttributeDefinitionRepositoryInterface — bind a fake
    $container->bind(
        AttributeDefinitionRepositoryInterface::class,
        FakeAttributeDefinitionRepository::class,
    );

    $accessor = $container->get(AttributeValueAccessorInterface::class);

    expect($accessor)->toBeInstanceOf(ProductAttributeAccessor::class);
});

it('registers the product entity class in the attribute entity-class map after boot', function (): void {
    $container = bootCatalogAttributeModuleContainer();

    $map = $container->get(AttributeEntityClassMap::class);

    expect($map->all())->toHaveKey('product')
        ->and($map->all()['product'])->toBe(Product::class);
});

it(
    'makes the definition service reject a custom product code that collides with a native column when the registry maps product to Product',
    function (): void {
        $container = bootCatalogAttributeModuleContainer();
    
        $map = $container->get(AttributeEntityClassMap::class);
    
        expect($map->all())->toHaveKey('product');
    
        // Verify that the entity class map for 'product' resolves to Product::class
    // which has 'sku', 'name', etc. as reserved codes
    $entityClass = $map->all()['product'];
        $reservedProvider = new ReservedCodeProvider(new EntityMetadataFactory());
        $reserved = $reservedProvider->reservedCodes($entityClass);
    
        expect($reserved)->toContain('sku')
            ->and($reserved)->toContain('name');
    }
);

it(
    'throws DuplicateEntityClassRegistrationException when a different class is registered for an existing entity type',
    function (): void {
        $map = new AttributeEntityClassMap();
        $map->register('product', Product::class);
    
        expect(fn () => $map->register('product', stdClass::class))
            ->toThrow(DuplicateEntityClassRegistrationException::class);
    }
);
