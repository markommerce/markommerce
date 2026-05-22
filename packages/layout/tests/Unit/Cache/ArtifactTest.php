<?php

declare(strict_types=1);

use Markommerce\Layout\Cache\ArtifactReader;
use Markommerce\Layout\Cache\ArtifactWriter;
use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedRepeatSlot;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\ResolvedLayout;
use Markommerce\Layout\Compiler\ResolvedPlace;
use Markommerce\Layout\Compiler\ResolvedRepeatSlot;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Source\RouteSource;
use Markommerce\Layout\Source\QuerySource;
use Markommerce\Layout\Source\ContextSource;
use Markommerce\Layout\Source\IteratedSource;
use Markommerce\Layout\Source\ParentDataSource;
use Markommerce\Layout\Source\ServiceSource;

// =============================================================================
// Helpers
// =============================================================================

function tempArtifactPath(): string
{
    return sys_get_temp_dir() . '/test_artifact_' . uniqid() . '.php';
}

// =============================================================================
// Requirement 1: it builds a PreparedTree from a resolved layout tree
// =============================================================================

it('builds a PreparedTree from a resolved layout tree', function (): void {
    $resolvedPlace = new ResolvedPlace(
        component: 'App\Component\FooComponent',
        name: 'foo.bar',
        props: ['id' => new RouteSource('id', 'int')],
        slots: [],
        decorators: [],
    );
    $resolvedLayout = new ResolvedLayout(
        handle: ['App\Controller\FooController', 'show'],
        handleKey: 'App\Controller\FooController::show',
        template: 'theme-blank::layout/1column',
        slots: ['content' => [$resolvedPlace]],
        context: [],
    );

    $builder = new PreparedTreeBuilder();
    $tree = $builder->build($resolvedLayout);

    expect($tree)->toBeInstanceOf(PreparedTree::class)
        ->and($tree->handleKey)->toBe('App\Controller\FooController::show')
        ->and($tree->template)->toBe('theme-blank::layout/1column')
        ->and($tree->slots)->toHaveKey('content')
        ->and($tree->slots['content'])->toHaveCount(1)
        ->and($tree->slots['content'][0])->toBeInstanceOf(PreparedPlace::class)
        ->and($tree->slots['content'][0]->component)->toBe('App\Component\FooComponent')
        ->and($tree->slots['content'][0]->props)->toHaveKey('id')
        ->and($tree->slots['content'][0]->props['id'])->toBeInstanceOf(RouteSource::class)
        ->and($tree->context)->toBe([]);
});

// =============================================================================
// Requirement 2: it preserves repeat slots in a PreparedTree
// =============================================================================

it('preserves repeat slots in a PreparedTree', function (): void {
    $childPlace = new ResolvedPlace(
        component: 'App\Component\ItemComponent',
        name: 'item',
        props: [],
        slots: [],
        decorators: [],
    );
    $repeatSlot = new ResolvedRepeatSlot(
        dataKey: 'products',
        yields: 'product',
        as: 'item',
        children: [$childPlace],
    );
    $resolvedLayout = new ResolvedLayout(
        handle: 'test_handle',
        handleKey: 'test_handle',
        template: null,
        slots: ['list' => $repeatSlot],
        context: [],
    );

    $builder = new PreparedTreeBuilder();
    $tree = $builder->build($resolvedLayout);

    expect($tree->slots)->toHaveKey('list')
        ->and($tree->slots['list'])->toBeInstanceOf(PreparedRepeatSlot::class)
        ->and($tree->slots['list']->dataKey)->toBe('products')
        ->and($tree->slots['list']->yields)->toBe('product')
        ->and($tree->slots['list']->as)->toBe('item')
        ->and($tree->slots['list']->children)->toHaveCount(1)
        ->and($tree->slots['list']->children[0])->toBeInstanceOf(PreparedPlace::class)
        ->and($tree->slots['list']->children[0]->component)->toBe('App\Component\ItemComponent');
});

// =============================================================================
// Requirement 3: it preserves decorator chains in a PreparedTree
// =============================================================================

it('preserves decorator chains in a PreparedTree', function (): void {
    $resolvedPlace = new ResolvedPlace(
        component: 'App\Component\FooComponent',
        name: 'foo',
        props: [],
        slots: [],
        decorators: ['App\Decorator\AuthDecorator', 'App\Decorator\CacheDecorator'],
    );
    $resolvedLayout = new ResolvedLayout(
        handle: 'test_handle',
        handleKey: 'test_handle',
        template: null,
        slots: ['content' => [$resolvedPlace]],
        context: [],
    );

    $builder = new PreparedTreeBuilder();
    $tree = $builder->build($resolvedLayout);

    expect($tree->slots['content'][0]->decorators)->toBe([
        'App\Decorator\AuthDecorator',
        'App\Decorator\CacheDecorator',
    ]);
});

// =============================================================================
// Requirement 4: it writes an artifact file that returns an array of PreparedTrees
// =============================================================================

it('writes an artifact file that returns an array of PreparedTrees', function (): void {
    $place = new PreparedPlace(
        component: 'App\Component\FooComponent',
        name: 'foo',
        props: [],
        slots: [],
        decorators: [],
    );
    $tree = new PreparedTree(
        handleKey: 'App\Controller\FooController::show',
        template: 'theme-blank::layout/1column',
        slots: ['content' => [$place]],
        context: [],
    );

    $path = tempArtifactPath();
    $writer = new ArtifactWriter($path);
    $writer->write(['App\Controller\FooController::show' => $tree]);

    expect(file_exists($path))->toBeTrue();

    $loaded = require $path;
    expect($loaded)->toBeArray()
        ->and($loaded)->toHaveKey('App\Controller\FooController::show')
        ->and($loaded['App\Controller\FooController::show'])->toBeInstanceOf(PreparedTree::class)
        ->and($loaded['App\Controller\FooController::show']->handleKey)->toBe('App\Controller\FooController::show')
        ->and($loaded['App\Controller\FooController::show']->template)->toBe('theme-blank::layout/1column')
        ->and($loaded['App\Controller\FooController::show']->slots['content'][0])->toBeInstanceOf(PreparedPlace::class);

    @unlink($path);
});

// =============================================================================
// Requirement 6: it creates the cache directory when writing if it does not exist
// =============================================================================

it('creates the cache directory when writing if it does not exist', function (): void {
    $dir = sys_get_temp_dir() . '/test_cache_' . uniqid();
    $path = $dir . '/layouts.php';

    expect(is_dir($dir))->toBeFalse();

    $tree = new PreparedTree(
        handleKey: 'test_handle',
        template: null,
        slots: [],
        context: [],
    );
    $writer = new ArtifactWriter($path);
    $writer->write(['test_handle' => $tree]);

    expect(is_dir($dir))->toBeTrue()
        ->and(file_exists($path))->toBeTrue();

    @unlink($path);
    @rmdir($dir);
});

// =============================================================================
// Requirement 5: it round-trips Source objects through the artifact
// =============================================================================

it('round-trips Source objects through the artifact', function (): void {
    $place = new PreparedPlace(
        component: 'App\Component\FooComponent',
        name: 'foo',
        props: [
            'routeId' => new RouteSource('id', 'int'),
            'queryPage' => new QuerySource('page', 1, 'int'),
            'ctx' => new ContextSource('user.token', 'user.name'),
            'iter' => new IteratedSource('product', 'product.name'),
            'parent' => new ParentDataSource('parentKey', 'string'),
            'svc' => new ServiceSource('App\Service\MyService'),
        ],
        slots: [],
        decorators: [],
    );
    $tree = new PreparedTree(
        handleKey: 'test_handle',
        template: null,
        slots: ['content' => [$place]],
        context: [],
    );

    $path = tempArtifactPath();
    $writer = new ArtifactWriter($path);
    $writer->write(['test_handle' => $tree]);

    $loaded = require $path;
    $loadedProps = $loaded['test_handle']->slots['content'][0]->props;

    expect($loadedProps['routeId'])->toBeInstanceOf(RouteSource::class)
        ->and($loadedProps['routeId']->name)->toBe('id')
        ->and($loadedProps['routeId']->as)->toBe('int')
        ->and($loadedProps['queryPage'])->toBeInstanceOf(QuerySource::class)
        ->and($loadedProps['queryPage']->name)->toBe('page')
        ->and($loadedProps['queryPage']->default)->toBe(1)
        ->and($loadedProps['queryPage']->as)->toBe('int')
        ->and($loadedProps['ctx'])->toBeInstanceOf(ContextSource::class)
        ->and($loadedProps['ctx']->token)->toBe('user.token')
        ->and($loadedProps['ctx']->path)->toBe('user.name')
        ->and($loadedProps['iter'])->toBeInstanceOf(IteratedSource::class)
        ->and($loadedProps['iter']->token)->toBe('product')
        ->and($loadedProps['iter']->path)->toBe('product.name')
        ->and($loadedProps['parent'])->toBeInstanceOf(ParentDataSource::class)
        ->and($loadedProps['parent']->key)->toBe('parentKey')
        ->and($loadedProps['parent']->as)->toBe('string')
        ->and($loadedProps['svc'])->toBeInstanceOf(ServiceSource::class)
        ->and($loadedProps['svc']->class)->toBe('App\Service\MyService');

    @unlink($path);
});

// =============================================================================
// Requirement 7: it reads a written artifact back into PreparedTrees
// =============================================================================

it('reads a written artifact back into PreparedTrees', function (): void {
    $place = new PreparedPlace(
        component: 'App\Component\FooComponent',
        name: 'foo',
        props: ['id' => new RouteSource('id', 'int')],
        slots: [],
        decorators: ['App\Decorator\SomeDecorator'],
    );
    $tree = new PreparedTree(
        handleKey: 'App\Controller\FooController::show',
        template: 'theme-blank::layout/1column',
        slots: ['content' => [$place]],
        context: [new Provide('user', 'App\Provider\UserProvider', ['foo' => 'bar'])],
    );

    $path = tempArtifactPath();
    $writer = new ArtifactWriter($path);
    $writer->write(['App\Controller\FooController::show' => $tree]);

    $reader = new ArtifactReader($path);
    $loaded = $reader->read();

    expect($loaded)->toHaveKey('App\Controller\FooController::show');
    $loadedTree = $loaded['App\Controller\FooController::show'];
    expect($loadedTree)->toBeInstanceOf(PreparedTree::class)
        ->and($loadedTree->handleKey)->toBe('App\Controller\FooController::show')
        ->and($loadedTree->template)->toBe('theme-blank::layout/1column')
        ->and($loadedTree->slots['content'][0])->toBeInstanceOf(PreparedPlace::class)
        ->and($loadedTree->slots['content'][0]->component)->toBe('App\Component\FooComponent')
        ->and($loadedTree->slots['content'][0]->props['id'])->toBeInstanceOf(RouteSource::class)
        ->and($loadedTree->slots['content'][0]->decorators)->toBe(['App\Decorator\SomeDecorator'])
        ->and($loadedTree->context[0])->toBeInstanceOf(Provide::class)
        ->and($loadedTree->context[0]->token)->toBe('user');

    @unlink($path);
});

// =============================================================================
// Requirement 8: it throws a clear error when reading a missing artifact
// =============================================================================

it('throws a clear error when reading a missing artifact', function (): void {
    $path = tempArtifactPath() . '_nonexistent.php';
    $reader = new ArtifactReader($path);
    expect(fn() => $reader->read())->toThrow(\RuntimeException::class);
});
