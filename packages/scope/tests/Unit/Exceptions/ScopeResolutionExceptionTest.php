<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Scope\Exceptions\ScopeResolutionException;

it('ScopeResolutionException resolverFailed preserves the original throwable as previous', function (): void {
    $original = new RuntimeException('something went wrong');
    $exception = ScopeResolutionException::resolverFailed('App\\Resolver\\StoreResolver', 'store', $original);

    expect($exception->getPrevious())->toBe($original);
});

it('ScopeResolutionException resolverFailed message names the resolver class and axis', function (): void {
    $original = new RuntimeException('something went wrong');
    $exception = ScopeResolutionException::resolverFailed('App\\Resolver\\StoreResolver', 'store', $original);

    expect($exception->getMessage())
        ->toContain('App\\Resolver\\StoreResolver')
        ->and($exception->getMessage())
        ->toContain('store');
});

it(
    'ScopeResolutionException invalidPath message names the offending path the resolver and the axis',
    function (): void {
        $exception = ScopeResolutionException::invalidPath('App\\Resolver\\StoreResolver', 'store', 'unknown/path');
    
        expect($exception->getMessage())
            ->toContain('App\\Resolver\\StoreResolver')
            ->and($exception->getMessage())
            ->toContain('store')
            ->and($exception->getMessage())
            ->toContain('unknown/path');
    }
);

it('both exception classes extend MarkoException', function (): void {
    $original = new RuntimeException('err');
    expect(ScopeResolutionException::resolverFailed('Foo', 'bar', $original))->toBeInstanceOf(MarkoException::class);
});
