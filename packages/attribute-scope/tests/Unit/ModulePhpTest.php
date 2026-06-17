<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Tests\Support\FakeAttributeDefinitionRepository;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\AttributeScope\ScopedOptionLabelResolver;

/**
 * Boots a fresh container using the attribute-scope module.php file.
 */
function bootAttributeScopeModuleContainer(): ContainerInterface
{
    $scopeRawConfig = require dirname(__DIR__, 3) . '/scope/config/scope.php';
    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';
    $attributeModule = require dirname(__DIR__, 3) . '/attribute/module.php';
    $attributeScopeModule = require dirname(__DIR__, 2) . '/module.php';

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

    foreach ([$scopeModule, $attributeModule, $attributeScopeModule] as $module) {
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

it('links AttributeOptionScopedLabels as an extender of AttributeOption after boot', function (): void {
    $container = bootAttributeScopeModuleContainer();

    $factory = $container->get(EntityMetadataFactory::class);
    $metadata = $factory->parse(AttributeOption::class);

    expect($metadata->extenders)->toContain(AttributeOptionScopedLabels::class);
});

it('resolves the ScopedOptionLabelResolver from the container', function (): void {
    $container = bootAttributeScopeModuleContainer();

    $resolver = $container->get(ScopedOptionLabelResolver::class);

    expect($resolver)->toBeInstanceOf(ScopedOptionLabelResolver::class);
});
