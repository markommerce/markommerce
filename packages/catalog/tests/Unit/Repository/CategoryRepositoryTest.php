<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Repository;

use Marko\Database\Repository\Repository;
use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Repository\CategoryRepository;
use Markommerce\Catalog\Repository\CategoryRepositoryInterface;
use ReflectionClass;

it('declares CategoryRepositoryInterface extending RepositoryInterface generic over Category', function (): void {
    $reflection = new ReflectionClass(CategoryRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();

    $docComment = $reflection->getDocComment();
    expect($docComment)->toContain('@template')
        ->and($docComment)->toContain('@extends RepositoryInterface<');
});

it('implements CategoryRepositoryInterface in CategoryRepository', function (): void {
    $reflection = new ReflectionClass(CategoryRepository::class);

    expect($reflection->isInterface())->toBeFalse()
        ->and($reflection->implementsInterface(CategoryRepositoryInterface::class))->toBeTrue()
        ->and($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('sets ENTITY_CLASS to the Category fully qualified class name', function (): void {
    $reflection = new ReflectionClass(CategoryRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Category::class);
});

it('inherits base contract methods find findAll save delete findBy findOneBy existsBy from the parent interface', function (): void {
    $reflection = new ReflectionClass(CategoryRepositoryInterface::class);

    $inheritedMethods = ['find', 'findAll', 'save', 'delete', 'findBy', 'findOneBy', 'existsBy'];

    foreach ($inheritedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "CategoryRepositoryInterface should inherit method: {$method}",
        );
    }
});
