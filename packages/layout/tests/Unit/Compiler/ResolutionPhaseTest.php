<?php

declare(strict_types=1);

use Markommerce\Layout\Compiler\ResolvedLayout;
use Markommerce\Layout\Compiler\ResolvedPlace;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Discovery\DiscoveredExtension;
use Markommerce\Layout\Discovery\DiscoveredLayout;
use Markommerce\Layout\Discovery\DiscoveryResult;
use Markommerce\Layout\Exception\DanglingAnchorException;
use Markommerce\Layout\Exception\ExtensionConflictException;
use Markommerce\Layout\Layout;
use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\Append;
use Markommerce\Layout\Operation\InsertAfter;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Operation\Replace;
use Markommerce\Layout\Operation\ReplaceProps;
use Markommerce\Layout\Operation\WrapWith;
use Markommerce\Layout\Place;

// =============================================================================
// Helpers
// =============================================================================

function makeDiscoveryResult(array $layouts, array $extensions = []): DiscoveryResult
{
    $discoveredLayouts = array_map(
        fn(Layout $layout) => new DiscoveredLayout($layout, 'fake://file.php'),
        $layouts,
    );
    $discoveredExtensions = array_map(
        fn(LayoutExtension $ext) => new DiscoveredExtension($ext, 'fake://ext.php'),
        $extensions,
    );
    return new DiscoveryResult(
        layouts: array_values($discoveredLayouts),
        extensions: array_values($discoveredExtensions),
    );
}

function makePlace(string $component = 'FooComponent', string $name = 'foo'): Place
{
    return new Place($component, $name, [], []);
}

// =============================================================================
// Tests
// =============================================================================

// =============================================================================
// LayoutDefinition stubs for extends chain testing
// =============================================================================

class RootLayoutDefinition implements LayoutDefinition
{
    public static function define(): Layout
    {
        return new Layout(
            handle: null,
            extends: null,
            context: [],
            slots: [
                'main' => [new Place('RootComponent', 'root', [], [])],
            ],
            template: 'layouts/root.latte',
        );
    }
}

class MiddleLayoutDefinition implements LayoutDefinition
{
    public static function define(): Layout
    {
        return new Layout(
            handle: null,
            extends: RootLayoutDefinition::class,
            context: [],
            slots: [
                'main' => [new Place('MiddleComponent', 'middle', [], [])],
            ],
            template: null,
        );
    }
}

class BaseLayoutDefinition implements LayoutDefinition
{
    public static function define(): Layout
    {
        return new Layout(
            handle: null,
            extends: null,
            context: [],
            slots: [
                'main' => [new Place('BaseHeader', 'header', [], [])],
            ],
            template: 'layouts/base.latte',
        );
    }
}

// =============================================================================
// Tests
// =============================================================================

it('resolves a layout with no extends into a single tree', function (): void {
    $place = makePlace('FooComponent', 'foo');
    $layout = new Layout(
        handle: ['App\Controller\FooController', 'show'],
        extends: null,
        context: [],
        slots: ['main' => [$place]],
        template: 'layouts/foo.latte',
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout]),
    );

    expect($result)->toHaveKey('App\Controller\FooController::show')
        ->and($result['App\Controller\FooController::show'])->toBeInstanceOf(ResolvedLayout::class)
        ->and($result['App\Controller\FooController::show']->handleKey)->toBe('App\Controller\FooController::show')
        ->and($result['App\Controller\FooController::show']->slots)->toHaveKey('main')
        ->and($result['App\Controller\FooController::show']->slots['main'])->toHaveCount(1);
});

it('merges a layout into its parent via the extends chain', function (): void {
    $childPlace = new Place('ChildComponent', 'child', [], []);
    $layout = new Layout(
        handle: 'child_handle',
        extends: BaseLayoutDefinition::class,
        context: [],
        slots: ['main' => [$childPlace]],
        template: null,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout]),
    );

    expect($result)->toHaveKey('child_handle')
        ->and($result['child_handle']->slots['main'])->toHaveCount(2)
        ->and($result['child_handle']->slots['main'][0]->component)->toBe('BaseHeader')
        ->and($result['child_handle']->slots['main'][1]->component)->toBe('ChildComponent')
        ->and($result['child_handle']->template)->toBe('layouts/base.latte');
});

it('resolves a transitive extends chain across three layouts', function (): void {
    $leafPlace = new Place('LeafComponent', 'leaf', [], []);
    $layout = new Layout(
        handle: 'leaf_handle',
        extends: MiddleLayoutDefinition::class,
        context: [],
        slots: ['main' => [$leafPlace]],
        template: null,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout]),
    );

    expect($result)->toHaveKey('leaf_handle')
        ->and($result['leaf_handle']->slots['main'])->toHaveCount(3)
        ->and($result['leaf_handle']->slots['main'][0]->component)->toBe('RootComponent')
        ->and($result['leaf_handle']->slots['main'][1]->component)->toBe('MiddleComponent')
        ->and($result['leaf_handle']->slots['main'][2]->component)->toBe('LeafComponent')
        ->and($result['leaf_handle']->template)->toBe('layouts/root.latte');
});

it('applies an InsertAfter operation placing a new placement next to its anchor', function (): void {
    $layout = new Layout(
        handle: 'test_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('ComponentA', 'a', [], []),
            new Place('ComponentB', 'b', [], []),
        ]],
    );
    $extension = new LayoutExtension(
        handle: 'test_handle',
        operations: [new InsertAfter('a', new Place('ComponentX', 'x', [], []))],
        priority: 0,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    );

    expect($result['test_handle']->slots['main'])->toHaveCount(3)
        ->and($result['test_handle']->slots['main'][0]->component)->toBe('ComponentA')
        ->and($result['test_handle']->slots['main'][1]->component)->toBe('ComponentX')
        ->and($result['test_handle']->slots['main'][2]->component)->toBe('ComponentB');
});

it('applies a Remove operation deleting the named placement', function (): void {
    $layout = new Layout(
        handle: 'remove_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('ComponentA', 'a', [], []),
            new Place('ComponentB', 'b', [], []),
        ]],
    );
    $extension = new LayoutExtension(
        handle: 'remove_handle',
        operations: [new Remove('a')],
        priority: 0,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    );

    expect($result['remove_handle']->slots['main'])->toHaveCount(1)
        ->and($result['remove_handle']->slots['main'][0]->component)->toBe('ComponentB');
});

it('applies a Replace operation swapping the named placement', function (): void {
    $layout = new Layout(
        handle: 'replace_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('OldComponent', 'old', [], []),
        ]],
    );
    $extension = new LayoutExtension(
        handle: 'replace_handle',
        operations: [new Replace('old', new Place('NewComponent', 'new', [], []))],
        priority: 0,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    );

    expect($result['replace_handle']->slots['main'])->toHaveCount(1)
        ->and($result['replace_handle']->slots['main'][0]->component)->toBe('NewComponent');
});

it('applies a MergeProps operation adding new props while keeping existing ones', function (): void {
    $layout = new Layout(
        handle: 'merge_props_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('SomeComponent', 'some', ['existing' => 'value'], []),
        ]],
    );
    $extension = new LayoutExtension(
        handle: 'merge_props_handle',
        operations: [new MergeProps('some', ['new_prop' => 'new_value'])],
        priority: 0,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    );

    expect($result['merge_props_handle']->slots['main'][0]->props)->toBe([
        'existing' => 'value',
        'new_prop' => 'new_value',
    ]);
});

it('applies a ReplaceProps operation discarding existing props', function (): void {
    $layout = new Layout(
        handle: 'replace_props_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('SomeComponent', 'some', ['old_prop' => 'old_value', 'another' => 'x'], []),
        ]],
    );
    $extension = new LayoutExtension(
        handle: 'replace_props_handle',
        operations: [new ReplaceProps('some', ['only_new' => 'fresh'])],
        priority: 0,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    );

    expect($result['replace_props_handle']->slots['main'][0]->props)->toBe([
        'only_new' => 'fresh',
    ]);
});

it('records a WrapWith operation as a decorator marker on the placement', function (): void {
    $layout = new Layout(
        handle: 'wrap_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('WrappedComponent', 'wrap_me', [], []),
        ]],
    );
    $extension = new LayoutExtension(
        handle: 'wrap_handle',
        operations: [new WrapWith('wrap_me', 'SomeDecorator')],
        priority: 0,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    );

    expect($result['wrap_handle']->slots['main'][0]->decorators)->toBe(['SomeDecorator']);
});

it('applies lower-priority extensions before higher-priority ones', function (): void {
    // Lower priority (0) wraps first, higher priority (10) wraps second.
    // Since higher applies last, its decorator is appended after lower's.
    $layout = new Layout(
        handle: 'priority_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('BaseComponent', 'base', [], []),
        ]],
    );
    $lowPriorityExtension = new LayoutExtension(
        handle: 'priority_handle',
        operations: [new WrapWith('base', 'LowDecorator')],
        priority: 0,
    );
    $highPriorityExtension = new LayoutExtension(
        handle: 'priority_handle',
        operations: [new WrapWith('base', 'HighDecorator')],
        priority: 10,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$highPriorityExtension, $lowPriorityExtension]),
    );

    expect($result['priority_handle']->slots['main'][0]->decorators)->toBe(['LowDecorator', 'HighDecorator']);
});

it('throws ExtensionConflictException when two same-priority extensions conflict on one anchor', function (): void {
    $layout = new Layout(
        handle: 'conflict_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('BaseComponent', 'base', [], []),
        ]],
    );
    $extensionA = new LayoutExtension(
        handle: 'conflict_handle',
        operations: [new Remove('base')],
        priority: 5,
    );
    $extensionB = new LayoutExtension(
        handle: 'conflict_handle',
        operations: [new Replace('base', new Place('NewComponent', 'base', [], []))],
        priority: 5,
    );

    expect(fn() => (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extensionA, $extensionB]),
    ))->toThrow(ExtensionConflictException::class);
});

it('throws DanglingAnchorException when an operation targets a name that does not exist', function (): void {
    $layout = new Layout(
        handle: 'dangling_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('SomeComponent', 'exists', [], []),
        ]],
    );
    $extension = new LayoutExtension(
        handle: 'dangling_handle',
        operations: [new Remove('nonexistent')],
        priority: 0,
    );

    expect(fn() => (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    ))->toThrow(DanglingAnchorException::class);
});

it('produces one resolved tree per handle key', function (): void {
    $layoutA = new Layout(
        handle: ['App\Controller\FooController', 'index'],
        extends: null,
        context: [],
        slots: ['main' => [new Place('ComponentA', 'a', [], [])]],
    );
    $layoutB = new Layout(
        handle: ['App\Controller\BarController', 'show'],
        extends: null,
        context: [],
        slots: ['main' => [new Place('ComponentB', 'b', [], [])]],
    );
    $layoutC = new Layout(
        handle: 'string_handle',
        extends: null,
        context: [],
        slots: ['sidebar' => [new Place('ComponentC', 'c', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layoutA, $layoutB, $layoutC]),
    );

    expect($result)->toHaveCount(3)
        ->and($result)->toHaveKey('App\Controller\FooController::index')
        ->and($result)->toHaveKey('App\Controller\BarController::show')
        ->and($result)->toHaveKey('string_handle');
});

it('resolves a handle-less base layout reached via an extends chain', function (): void {
    // BaseLayoutDefinition has handle: null and provides a 'header' placement.
    $childLayout = new Layout(
        handle: 'child_with_base',
        extends: BaseLayoutDefinition::class,
        context: [],
        slots: ['main' => [new Place('ChildContent', 'content', [], [])]],
        template: 'layouts/child.latte',
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$childLayout]),
    );

    // The base layout's 'main' slot ('header') is merged into the child.
    expect($result)->toHaveKey('child_with_base')
        ->and($result['child_with_base']->slots['main'])->toHaveCount(2)
        ->and($result['child_with_base']->slots['main'][0]->component)->toBe('BaseHeader')
        ->and($result['child_with_base']->slots['main'][1]->component)->toBe('ChildContent');
});

it('excludes a handle-less base layout from the routable handle map', function (): void {
    $handleLessLayout = new Layout(
        handle: null,
        extends: null,
        context: [],
        slots: ['main' => [new Place('BaseComponent', 'base', [], [])]],
        template: 'layouts/base.latte',
    );
    $routableLayout = new Layout(
        handle: 'routable_handle',
        extends: null,
        context: [],
        slots: ['main' => [new Place('RoutableComponent', 'routable', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$handleLessLayout, $routableLayout]),
    );

    expect($result)->toHaveCount(1)
        ->and($result)->toHaveKey('routable_handle')
        ->and($result)->not->toHaveKey('');
});

it('carries the effective template through the extends chain', function (): void {
    // Child with its own template: child's template wins.
    $childWithTemplate = new Layout(
        handle: 'child_own_template',
        extends: BaseLayoutDefinition::class,
        context: [],
        slots: [],
        template: 'layouts/child_own.latte',
    );

    // Child without template: inherits from base (layouts/base.latte).
    $childWithoutTemplate = new Layout(
        handle: 'child_no_template',
        extends: BaseLayoutDefinition::class,
        context: [],
        slots: [],
        template: null,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$childWithTemplate, $childWithoutTemplate]),
    );

    expect($result['child_own_template']->template)->toBe('layouts/child_own.latte')
        ->and($result['child_no_template']->template)->toBe('layouts/base.latte');
});
