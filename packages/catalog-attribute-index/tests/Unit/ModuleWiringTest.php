<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeIndex\IndexedAttributeReader;
use Markommerce\CatalogAttributeIndex\Query\AttributeExistsClause;
use Markommerce\Indexer\Registry\IndexerRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function buildWiringModuleContainer(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->singleton(IndexerRegistry::class);

    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($module['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    return $container;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('binds the attribute indexer in the container', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(AttributeIndexer::class);
});

it('binds the indexed attribute reader in the container', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(IndexedAttributeReader::class);
});

it('binds the attribute exists clause in the container', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(AttributeExistsClause::class);
});

it('binds the attribute facet query in the container', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(AttributeFacetQuery::class);
});
