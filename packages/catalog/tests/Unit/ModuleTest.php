<?php

declare(strict_types=1);

use Markommerce\Catalog\Repository\CategoryRepositoryInterface;
use Markommerce\Catalog\Repository\CategoryRepository;
use Markommerce\Catalog\Repository\ProductRepositoryInterface;
use Markommerce\Catalog\Repository\ProductRepository;
use Markommerce\Catalog\Service\CategoryServiceInterface;
use Markommerce\Catalog\Service\CategoryService;
use Markommerce\Catalog\Service\ProductServiceInterface;
use Markommerce\Catalog\Service\ProductService;
use Markommerce\Catalog\Service\ProductPriceServiceInterface;
use Markommerce\Catalog\Service\ProductPriceService;
use Markommerce\Catalog\Service\CategoryAssignmentServiceInterface;
use Markommerce\Catalog\Service\CategoryAssignmentService;

const MARKO_INFRASTRUCTURE_TYPES = [
    'Marko\\Database\\Connection\\ConnectionInterface',
    'Marko\\Database\\Entity\\EntityMetadataFactory',
    'Marko\\Database\\Entity\\EntityHydrator',
    'Marko\\Core\\Event\\EventDispatcherInterface',
    'Marko\\Database\\Connection\\TransactionInterface',
    'Marko\\Database\\Query\\QueryBuilderFactoryInterface',
    'Marko\\Database\\Entity\\RelationshipLoader',
];

it('returns an array with a bindings key from module.php', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    expect(file_exists($modulePath))->toBeTrue('module.php should exist');

    $module = require $modulePath;

    expect($module)->toBeArray();
    expect($module)->toHaveKey('bindings');
});

it('binds CategoryRepositoryInterface to CategoryRepository', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(CategoryRepositoryInterface::class);
    expect($bindings[CategoryRepositoryInterface::class])->toBe(CategoryRepository::class);
});

it('binds ProductRepositoryInterface to ProductRepository', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(ProductRepositoryInterface::class);
    expect($bindings[ProductRepositoryInterface::class])->toBe(ProductRepository::class);
});

it('binds CategoryServiceInterface to CategoryService', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(CategoryServiceInterface::class);
    expect($bindings[CategoryServiceInterface::class])->toBe(CategoryService::class);
});

it('binds ProductServiceInterface to ProductService', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(ProductServiceInterface::class);
    expect($bindings[ProductServiceInterface::class])->toBe(ProductService::class);
});

it('binds ProductPriceServiceInterface to ProductPriceService', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(ProductPriceServiceInterface::class);
    expect($bindings[ProductPriceServiceInterface::class])->toBe(ProductPriceService::class);
});

it('binds CategoryAssignmentServiceInterface to CategoryAssignmentService', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(CategoryAssignmentServiceInterface::class);
    expect($bindings[CategoryAssignmentServiceInterface::class])->toBe(CategoryAssignmentService::class);
});

it('ensures every bound interface key exists as a real interface', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    foreach ($bindings as $interface => $class) {
        expect(interface_exists($interface))->toBeTrue(
            "Expected {$interface} to be a real interface",
        );
    }
});

it('ensures every bound implementation class exists and implements its bound interface', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    foreach ($bindings as $interface => $class) {
        expect(class_exists($class))->toBeTrue(
            "Expected {$class} to be a real class",
        );

        $reflection = new ReflectionClass($class);
        expect($reflection->implementsInterface($interface))->toBeTrue(
            "Expected {$class} to implement {$interface}",
        );
    }
});

it('verifies that every constructor dependency of every bound concrete class is satisfiable by merging catalog\'s bindings with markommerce/money-moneyphp\'s bindings and the well-known Marko infrastructure types (ConnectionInterface, EntityMetadataFactory, EntityHydrator, EventDispatcherInterface, TransactionInterface, QueryBuilderFactoryInterface, RelationshipLoader). Catches "I forgot to bind X" before runtime.', function (): void {
    $catalogModule = require dirname(__DIR__, 2) . '/module.php';
    $moneyModule = require dirname(__DIR__, 3) . '/money-moneyphp/module.php';

    $mergedBindings = array_merge($moneyModule['bindings'], $catalogModule['bindings']);

    foreach ($catalogModule['bindings'] as $interface => $class) {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            continue;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                continue;
            }

            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === null) {
                continue;
            }

            $isNullable = $parameter->allowsNull();
            $isBound = array_key_exists($typeName, $mergedBindings);
            $isInfrastructure = in_array($typeName, MARKO_INFRASTRUCTURE_TYPES, true);

            expect($isNullable || $isBound || $isInfrastructure)->toBeTrue(
                "Constructor parameter \${$parameter->getName()} of type {$typeName} in {$class} is not satisfiable. " .
                "It is not bound in the merged bindings map, not a well-known Marko infrastructure type, and not nullable.",
            );
        }
    }
});
