<?php

declare(strict_types=1);

use Markommerce\Layout\Cache\ArtifactReader;
use Markommerce\Layout\Cache\ArtifactWriter;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\ResolvedLayout;
use Markommerce\Layout\Contracts\HandleProvider;
use Markommerce\Layout\Exceptions\InvalidLayoutFileException;
use Markommerce\Layout\Exceptions\InvalidSourceTypeException;
use Markommerce\Layout\Layout;
use Markommerce\Layout\ProvideHandle;
use Markommerce\Layout\Source\ContextSource;
use Markommerce\Layout\Source\IteratedSource;
use Markommerce\Layout\Source\ParentDataSource;
use Markommerce\Layout\Source\RouteSource;

// =============================================================================
// Requirement 1: it defines HandleProvider interface with provide method returning list of string
// =============================================================================

it('defines HandleProvider interface with provide method returning list of string', function (): void {
    $reflection = new ReflectionClass(HandleProvider::class);

    expect($reflection->isInterface())->toBeTrue();

    $method = $reflection->getMethod('provide');
    expect($method->isPublic())->toBeTrue();

    $params = $method->getParameters();
    expect(count($params))->toBe(1);
    expect($params[0]->getName())->toBe('props');

    $returnType = $method->getReturnType();
    expect((string) $returnType)->toBe('array');
});

// =============================================================================
// Requirement 2: it accepts a handleProviders list on Layout defaulting to empty
// =============================================================================

it('accepts a handleProviders list on Layout defaulting to empty', function (): void {
    $layout = new Layout(
        handle: 'some-handle',
        extends: null,
        context: [],
        slots: [],
    );

    expect($layout->handleProviders)->toBe([]);

    $provideHandle = new ProvideHandle(
        provider: HandleProvider::class,
        props: [],
    );

    $layoutWithProviders = new Layout(
        handle: 'some-handle',
        extends: null,
        context: [],
        slots: [],
        handleProviders: [$provideHandle],
    );

    expect($layoutWithProviders->handleProviders)->toBe([$provideHandle]);
});

// =============================================================================
// Requirement 3: it stores ProvideHandle value object at Markommerce\Layout\ProvideHandle with provider class-string and props map
// =============================================================================

it(
    'stores ProvideHandle value object at Markommerce\\Layout\\ProvideHandle with provider class-string and props map',
    function (): void {
        $provideHandle = new ProvideHandle(
            provider: HandleProvider::class,
            props: ['product' => new ContextSource('ProductToken', null)],
        );
    
        expect($provideHandle->provider)->toBe(HandleProvider::class);
        expect($provideHandle->props)->toHaveKey('product');
        expect($provideHandle->props['product'])->toBeInstanceOf(ContextSource::class);
    }
);

// =============================================================================
// Requirement 4: it adds handleProviders to PreparedTree defaulting to empty
// =============================================================================

it('adds handleProviders to PreparedTree defaulting to empty', function (): void {
    $tree = new PreparedTree(
        handleKey: 'some-handle',
        template: null,
        slots: [],
        context: [],
    );

    expect($tree->handleProviders)->toBe([]);

    $provideHandle = new ProvideHandle(
        provider: HandleProvider::class,
        props: [],
    );

    $treeWithProviders = new PreparedTree(
        handleKey: 'some-handle',
        template: null,
        slots: [],
        context: [],
        handleProviders: [$provideHandle],
    );

    expect($treeWithProviders->handleProviders)->toBe([$provideHandle]);
});

// =============================================================================
// Requirement 5: it serializes ProvideHandle entries into the compiled artifact via PhpCodeEmitter
// =============================================================================

it('serializes ProvideHandle entries into the compiled artifact via PhpCodeEmitter', function (): void {
    $provideHandle = new ProvideHandle(
        provider: HandleProvider::class,
        props: ['product' => new RouteSource('product_id', 'string')],
    );

    $tree = new PreparedTree(
        handleKey: 'some-handle',
        template: null,
        slots: [],
        context: [],
        handleProviders: [$provideHandle],
    );

    $path = sys_get_temp_dir() . '/test_handle_provider_' . uniqid() . '.php';
    $writer = new ArtifactWriter($path);
    $writer->write(['some-handle' => $tree]);

    expect(file_exists($path))->toBeTrue();

    $loaded = require $path;
    expect($loaded['some-handle']->handleProviders)->toHaveCount(1);
    expect($loaded['some-handle']->handleProviders[0])->toBeInstanceOf(ProvideHandle::class);
    expect($loaded['some-handle']->handleProviders[0]->provider)->toBe(HandleProvider::class);

    @unlink($path);
});

// =============================================================================
// Requirement 6: it round-trips a PreparedTree with handle providers through writer + reader
// =============================================================================

it('round-trips a PreparedTree with handle providers through writer + reader', function (): void {
    $provideHandle = new ProvideHandle(
        provider: HandleProvider::class,
        props: ['id' => new RouteSource('product_id', 'string')],
    );

    $tree = new PreparedTree(
        handleKey: 'some-handle',
        template: null,
        slots: [],
        context: [],
        handleProviders: [$provideHandle],
    );

    $path = sys_get_temp_dir() . '/test_handle_provider_roundtrip_' . uniqid() . '.php';
    $writer = new ArtifactWriter($path);
    $writer->write(['some-handle' => $tree]);

    $reader = new ArtifactReader($path);
    $loaded = $reader->read();

    expect($loaded)->toHaveKey('some-handle');
    $loadedTree = $loaded['some-handle'];
    expect($loadedTree->handleProviders)->toHaveCount(1);
    expect($loadedTree->handleProviders[0])->toBeInstanceOf(ProvideHandle::class);
    expect($loadedTree->handleProviders[0]->provider)->toBe(HandleProvider::class);
    expect($loadedTree->handleProviders[0]->props['id'])->toBeInstanceOf(RouteSource::class);

    @unlink($path);
});

// =============================================================================
// Requirement 7: it throws InvalidLayoutFileException when a provider class does not implement HandleProvider
// =============================================================================

it('throws InvalidLayoutFileException when a provider class does not implement HandleProvider', function (): void {
    $resolvedLayout = new ResolvedLayout(
        handle: 'some-handle',
        handleKey: 'some-handle',
        template: null,
        slots: [],
        context: [],
        handleProviders: [
            new ProvideHandle(
                provider: stdClass::class,
                props: [],
            ),
        ],
    );

    $builder = new PreparedTreeBuilder();
    expect(fn () => $builder->build($resolvedLayout))
        ->toThrow(InvalidLayoutFileException::class);
});

// =============================================================================
// Requirement 8: it throws InvalidSourceTypeException when ProvideHandle::props contains a ParentDataSource
// =============================================================================

it('throws InvalidSourceTypeException when ProvideHandle::props contains a ParentDataSource', function (): void {
    $resolvedLayout = new ResolvedLayout(
        handle: 'some-handle',
        handleKey: 'some-handle',
        template: null,
        slots: [],
        context: [],
        handleProviders: [
            new ProvideHandle(
                provider: HandleProvider::class,
                props: ['parent' => new ParentDataSource('key', 'string')],
            ),
        ],
    );

    $builder = new PreparedTreeBuilder();
    expect(fn () => $builder->build($resolvedLayout))
        ->toThrow(InvalidSourceTypeException::class);
});

// =============================================================================
// Requirement 9: it throws InvalidSourceTypeException when ProvideHandle::props contains an IteratedSource
// =============================================================================

it('throws InvalidSourceTypeException when ProvideHandle::props contains an IteratedSource', function (): void {
    $resolvedLayout = new ResolvedLayout(
        handle: 'some-handle',
        handleKey: 'some-handle',
        template: null,
        slots: [],
        context: [],
        handleProviders: [
            new ProvideHandle(
                provider: HandleProvider::class,
                props: ['item' => new IteratedSource('product', null)],
            ),
        ],
    );

    $builder = new PreparedTreeBuilder();
    expect(fn () => $builder->build($resolvedLayout))
        ->toThrow(InvalidSourceTypeException::class);
});
