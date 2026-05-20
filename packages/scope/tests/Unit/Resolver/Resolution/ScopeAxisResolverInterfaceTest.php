<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

it('lives in the Markommerce\Scope\Resolver\Resolution namespace', function (): void {
    $reflection = new ReflectionClass(ScopeAxisResolverInterface::class);

    expect($reflection->getNamespaceName())->toBe('Markommerce\Scope\Resolver\Resolution');
});

it('is declared as an interface not a class', function (): void {
    $reflection = new ReflectionClass(ScopeAxisResolverInterface::class);

    expect($reflection->isInterface())->toBeTrue();
});

it('has no other methods or constants', function (): void {
    $reflection = new ReflectionClass(ScopeAxisResolverInterface::class);

    $methods = $reflection->getMethods();
    expect($methods)->toHaveCount(1);
    expect($methods[0]->getName())->toBe('resolve');

    $constants = $reflection->getConstants();
    expect($constants)->toHaveCount(0);
});

it('documents that null means defer to next resolver in chain', function (): void {
    $reflection = new ReflectionClass(ScopeAxisResolverInterface::class);
    $method = $reflection->getMethod('resolve');
    $docComment = $method->getDocComment();

    expect($docComment)->toBeString();
    expect($docComment)->toContain('null');
    expect($docComment)->toContain('defer');
    expect($docComment)->toContain('next resolver');
});

it('declares a resolve method taking ScopeAxis and ScopeResolutionContext returning nullable string', function (): void {
    $reflection = new ReflectionClass(ScopeAxisResolverInterface::class);

    expect($reflection->hasMethod('resolve'))->toBeTrue();

    $method = $reflection->getMethod('resolve');
    $params = $method->getParameters();

    expect($params)->toHaveCount(2);

    expect($params[0]->getName())->toBe('scopeAxis');
    expect($params[0]->getType()?->getName())->toBe(ScopeAxis::class);

    expect($params[1]->getName())->toBe('scopeResolutionContext');
    expect($params[1]->getType()?->getName())->toBe(ScopeResolutionContext::class);

    $returnType = $method->getReturnType();
    expect($returnType)->toBeInstanceOf(ReflectionNamedType::class);
    assert($returnType instanceof ReflectionNamedType);
    expect($returnType->getName())->toBe('string');
    expect($returnType->allowsNull())->toBeTrue();
});
