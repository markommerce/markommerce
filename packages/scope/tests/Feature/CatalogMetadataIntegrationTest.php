<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a real Container with scope's module fully wired (but boot not run).
 */
function buildScopeContainer(): Container
{
    $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['singletons'] as $singleton) {
        $container->singleton($singleton);
    }

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    return $container;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'it produces correct metadata end-to-end for Markommerce\Catalog\Entity\Product after wiring (locale axis on name and description)',
    function (): void {
        DefaultScopeGuard::reset();

        $container = buildScopeContainer();
        $factory = $container->get(ScopeMetadataFactory::class);

        $metadata = $factory->for(Product::class);

        expect($metadata->scopedProperties())->toContain('name')
            ->and($metadata->scopedProperties())->toContain('description')
            ->and($metadata->axesForProperty('name'))->toBe(['locale'])
            ->and($metadata->axesForProperty('description'))->toBe(['locale']);
    },
);

it(
    'it produces correct metadata end-to-end for Markommerce\Catalog\Entity\Category after wiring',
    function (): void {
        DefaultScopeGuard::reset();

        $container = buildScopeContainer();
        $factory = $container->get(ScopeMetadataFactory::class);

        $metadata = $factory->for(Category::class);

        expect($metadata->scopedProperties())->toContain('name')
            ->and($metadata->scopedProperties())->toContain('description')
            ->and($metadata->axesForProperty('name'))->toBe(['locale'])
            ->and($metadata->axesForProperty('description'))->toBe(['locale']);
    },
);
