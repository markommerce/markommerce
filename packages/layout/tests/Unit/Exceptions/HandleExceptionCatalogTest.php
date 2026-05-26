<?php

declare(strict_types=1);

use Markommerce\Layout\Exceptions\ChainedHandleProviderException;
use Markommerce\Layout\Exceptions\CircularInheritanceException;
use Markommerce\Layout\Exceptions\DefaultHandleConflictException;
use Markommerce\Layout\Exceptions\DuplicateContextTokenException;
use Markommerce\Layout\Exceptions\DynamicHandleConflictException;
use Markommerce\Layout\Exceptions\LayoutException;
use Markommerce\Layout\Exceptions\UnknownDynamicHandleException;
use Markommerce\Layout\Exceptions\UnknownParentHandleException;

it('throws CircularInheritanceException with the inheritance chain in context', function (): void {
    $exception = CircularInheritanceException::forChain(['handle_a', 'handle_b', 'handle_a']);

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('handle_a')
        ->and($exception->getMessage())->toContain('handle_b')
        ->and($exception->getContext())->toContain('handle_a')
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('throws UnknownParentHandleException with the missing parent name and suggestion to define it', function (): void {
    $exception = UnknownParentHandleException::forParent('parent_handle', 'child_handle');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('parent_handle')
        ->and($exception->getMessage())->toContain('child_handle')
        ->and($exception->getSuggestion())->toContain('parent_handle')
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it(
    'throws DefaultHandleConflictException when a default handle declares an extends or inherits chain',
    function (): void {
        $exception = DefaultHandleConflictException::forField('inherits');

        expect($exception)->toBeInstanceOf(LayoutException::class)
            ->and($exception->getMessage())->toContain('default')
            ->and($exception->getMessage())->toContain('inherits')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    },
);

it('throws DefaultHandleConflictException when a default handle declares handleProviders', function (): void {
    $exception = DefaultHandleConflictException::forField('handleProviders');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('default')
        ->and($exception->getMessage())->toContain('handleProviders')
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('throws DynamicHandleConflictException when two merged trees declare the same placement name', function (): void {
    $exception = DynamicHandleConflictException::forCollidingPlacement('header_logo', 'base_handle', 'promo_handle');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('header_logo')
        ->and($exception->getMessage())->toContain('base_handle')
        ->and($exception->getMessage())->toContain('promo_handle')
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it(
    'throws UnknownDynamicHandleException when a HandleProvider returns a handle key not in the artifact',
    function (): void {
        $exception = UnknownDynamicHandleException::forHandle('missing_handle', 'MyHandleProvider');

        expect($exception)->toBeInstanceOf(LayoutException::class)
            ->and($exception->getMessage())->toContain('missing_handle')
            ->and($exception->getMessage())->toContain('MyHandleProvider')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    },
);

it(
    'throws DuplicateContextTokenException when an inherits or default merge introduces a duplicate token',
    function (): void {
        $exception = DuplicateContextTokenException::forToken('product_id', 'parent_handle', 'child_handle');

        expect($exception)->toBeInstanceOf(LayoutException::class)
            ->and($exception->getMessage())->toContain('product_id')
            ->and($exception->getMessage())->toContain('parent_handle')
            ->and($exception->getMessage())->toContain('child_handle')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    },
);

it(
    'throws ChainedHandleProviderException when a dynamic handle\'s tree itself declares handleProviders',
    function (): void {
        $exception = ChainedHandleProviderException::forChain('MyDynamicProvider', 'dynamic_handle');

        expect($exception)->toBeInstanceOf(LayoutException::class)
            ->and($exception->getMessage())->toContain('MyDynamicProvider')
            ->and($exception->getMessage())->toContain('dynamic_handle')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    },
);

it('each new exception extends LayoutException', function (): void {
    $exceptions = [
        CircularInheritanceException::forChain(['a', 'b', 'a']),
        UnknownParentHandleException::forParent('parent', 'child'),
        DefaultHandleConflictException::forField('inherits'),
        DynamicHandleConflictException::forCollidingPlacement('placement', 'base', 'dynamic'),
        UnknownDynamicHandleException::forHandle('handle', 'Provider'),
        DuplicateContextTokenException::forToken('token', 'source', 'target'),
        ChainedHandleProviderException::forChain('Provider', 'handle'),
    ];

    foreach ($exceptions as $exception) {
        expect($exception)->toBeInstanceOf(LayoutException::class);
    }
});

it('each new exception is documented in the package README exception table', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../../README.md');
    expect($readme)->not->toBeFalse();
    /** @var string $readme */

    expect($readme)->toContain('CircularInheritanceException')
        ->and($readme)->toContain('UnknownParentHandleException')
        ->and($readme)->toContain('DefaultHandleConflictException')
        ->and($readme)->toContain('DynamicHandleConflictException')
        ->and($readme)->toContain('UnknownDynamicHandleException')
        ->and($readme)->toContain('DuplicateContextTokenException')
        ->and($readme)->toContain('ChainedHandleProviderException');
});
