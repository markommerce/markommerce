<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Scope\Exceptions\InvalidResolverConfigException;

it('InvalidResolverConfigException unknownClass produces message naming the class and axis', function (): void {
    $exception = InvalidResolverConfigException::unknownClass('App\\Resolver\\StoreResolver', 'store');

    expect($exception->getMessage())
        ->toContain('App\\Resolver\\StoreResolver')
        ->and($exception->getMessage())
        ->toContain('store');
});

it('InvalidResolverConfigException unknownClass produces suggestion pointing at the config path', function (): void {
    $exception = InvalidResolverConfigException::unknownClass('App\\Resolver\\StoreResolver', 'store');

    expect($exception->getSuggestion())->not->toBeEmpty();
});

it('InvalidResolverConfigException missingClassKey identifies the array index and axis', function (): void {
    $exception = InvalidResolverConfigException::missingClassKey(2, 'locale');

    expect($exception->getMessage())
        ->toContain('2')
        ->and($exception->getMessage())
        ->toContain('locale');
});

it(
    'InvalidResolverConfigException notImplementingInterface names ScopeAxisResolverInterface in suggestion',
    function (): void {
        $exception = InvalidResolverConfigException::notImplementingInterface('App\\Resolver\\BadResolver', 'store');

        expect($exception->getSuggestion())->toContain('ScopeAxisResolverInterface');
    },
);

it('both exception classes extend MarkoException', function (): void {
    expect(InvalidResolverConfigException::unknownClass('Foo', 'bar'))->toBeInstanceOf(MarkoException::class);
});
