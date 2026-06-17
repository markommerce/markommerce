<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Services\AttributeDefinitionService;
use Markommerce\Attribute\Tests\Support\FakeAttributeDefinitionRepository;
use Markommerce\Attribute\Type\FacetKind;

/**
 * Boots a fresh container using the attribute module.php file.
 */
function bootAttributeModuleContainer(): ContainerInterface
{
    $moduleArray = require dirname(__DIR__, 2) . '/module.php';

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(PreferenceRegistry::class, $preferenceRegistry);

    // Register bindings from module.php
    foreach ($moduleArray['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // Register singletons from module.php
    foreach ($moduleArray['singletons'] ?? [] as $key => $value) {
        if (is_int($key)) {
            $container->singleton($value);
        } else {
            $container->bind($key, $value);
            $container->singleton($key);
        }
    }

    // Call the boot closure
    if (isset($moduleArray['boot']) && $moduleArray['boot'] instanceof Closure) {
        $container->call($moduleArray['boot']);
    }

    return $container;
}

// ─────────────────────────────────────────────────────────
// Requirements
// ─────────────────────────────────────────────────────────

it('registers all eight built-in attribute types in the registry after boot', function (): void {
    $container = bootAttributeModuleContainer();

    $registry = $container->get(AttributeTypeRegistry::class);

    $all = $registry->all();

    expect($all)->toHaveCount(8)
        ->and($all)->toHaveKey('text')
        ->and($all)->toHaveKey('int')
        ->and($all)->toHaveKey('decimal')
        ->and($all)->toHaveKey('bool')
        ->and($all)->toHaveKey('date')
        ->and($all)->toHaveKey('select')
        ->and($all)->toHaveKey('multiselect')
        ->and($all)->toHaveKey('entityRef');
});

it('binds the definition service interface to its implementation', function (): void {
    $container = bootAttributeModuleContainer();

    // AttributeDefinitionService depends on AttributeDefinitionRepositoryInterface which
    // lives in the driver package (attribute-pgsql) — bind a fake for this test.
    $container->bind(AttributeDefinitionRepositoryInterface::class, FakeAttributeDefinitionRepository::class);

    $service = $container->get(AttributeDefinitionService::class);

    expect($service)->toBeInstanceOf(AttributeDefinitionService::class);
});

it('resolves the AttributeTypeRegistry as a singleton from the container', function (): void {
    $container = bootAttributeModuleContainer();

    $registry1 = $container->get(AttributeTypeRegistry::class);
    $registry2 = $container->get(AttributeTypeRegistry::class);

    expect($registry1)->toBeInstanceOf(AttributeTypeRegistry::class)
        ->and($registry1)->toBe($registry2);
});

it('lets a downstream registration override a built-in type code', function (): void {
    $container = bootAttributeModuleContainer();

    $registry = $container->get(AttributeTypeRegistry::class);

    // A downstream consumer registers a custom type with the same 'text' code
    $customType = new class () implements AttributeTypeInterface
    {
        public function code(): string
        {
            return 'text';
        }

        public function cast(
            mixed $raw,
            AttributeDefinitionInterface $definition,
        ): mixed
        {
            return strtoupper((string) $raw);
        }

        public function serialize(mixed $value): mixed
        {
            return $value;
        }

        public function deserialize(mixed $stored): mixed
        {
            return $stored;
        }

        public function facetKind(): FacetKind
        {
            return FacetKind::Term;
        }
    };

    $registry->register($customType);

    expect($registry->get('text'))->toBe($customType);
});
