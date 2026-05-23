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

// =============================================================================
// Task 025: Layout own operations
// =============================================================================

it('applies layout operations after resolving the extends chain', function (): void {
    // BaseLayoutDefinition provides a 'header' placement in 'main'.
    // The child layout has an operation that appends a placement to 'main'.
    $childLayout = new Layout(
        handle: 'ops_after_extends',
        extends: BaseLayoutDefinition::class,
        context: [],
        slots: [],
        operations: [new \Markommerce\Layout\Operation\Append('main', new Place('NewComponent', 'new', [], []))],
        template: null,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$childLayout]),
    );

    // After extends chain: main = [header]
    // After own operations (Append): main = [header, new]
    expect($result['ops_after_extends']->slots['main'])->toHaveCount(2)
        ->and($result['ops_after_extends']->slots['main'][0]->component)->toBe('BaseHeader')
        ->and($result['ops_after_extends']->slots['main'][1]->component)->toBe('NewComponent');
});

it('applies layout operations before applying extension-file operations', function (): void {
    // Layout has its own operation: Append 'own' to 'main'.
    // Extension file has an operation: InsertAfter 'own' with 'ext'.
    // Expected order: [own, ext] — the extension can target 'own' because layout ops ran first.
    $layout = new Layout(
        handle: 'ops_order_handle',
        extends: null,
        context: [],
        slots: ['main' => [new Place('BaseComponent', 'base', [], [])]],
        operations: [new \Markommerce\Layout\Operation\Append('main', new Place('OwnComponent', 'own', [], []))],
    );
    $extension = new LayoutExtension(
        handle: 'ops_order_handle',
        operations: [new \Markommerce\Layout\Operation\InsertAfter('own', new Place('ExtComponent', 'ext', [], []))],
        priority: 0,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout], [$extension]),
    );

    // main = [base, own, ext]
    expect($result['ops_order_handle']->slots['main'])->toHaveCount(3)
        ->and($result['ops_order_handle']->slots['main'][0]->component)->toBe('BaseComponent')
        ->and($result['ops_order_handle']->slots['main'][1]->component)->toBe('OwnComponent')
        ->and($result['ops_order_handle']->slots['main'][2]->component)->toBe('ExtComponent');
});

it('allows a layout to Remove a placement provided by the extended shell', function (): void {
    // BaseLayoutDefinition provides 'header' in 'main'.
    // The child layout removes 'header' via its own operations.
    $childLayout = new Layout(
        handle: 'remove_from_shell',
        extends: BaseLayoutDefinition::class,
        context: [],
        slots: [],
        operations: [new \Markommerce\Layout\Operation\Remove('header')],
        template: null,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$childLayout]),
    );

    // After extends: main = [header]
    // After own Remove('header'): main = []
    expect($result['remove_from_shell']->slots['main'])->toHaveCount(0);
});

it('allows a layout to MergeProps onto a placement provided by extends', function (): void {
    // BaseLayoutDefinition provides 'header' in 'main' with no props.
    // The child layout merges props onto 'header' via own operations.
    $childLayout = new Layout(
        handle: 'merge_from_shell',
        extends: BaseLayoutDefinition::class,
        context: [],
        slots: [],
        operations: [new \Markommerce\Layout\Operation\MergeProps('header', ['size' => 'large'])],
        template: null,
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$childLayout]),
    );

    expect($result['merge_from_shell']->slots['main'][0]->name)->toBe('header')
        ->and($result['merge_from_shell']->slots['main'][0]->props)->toBe(['size' => 'large']);
});

it('throws DanglingAnchorException when a layout operation targets a nonexistent name', function (): void {
    $layout = new Layout(
        handle: 'dangling_own_op',
        extends: null,
        context: [],
        slots: ['main' => [new Place('SomeComponent', 'exists', [], [])]],
        operations: [new \Markommerce\Layout\Operation\Remove('nonexistent')],
    );

    expect(fn() => (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout]),
    ))->toThrow(DanglingAnchorException::class);
});

it('preserves existing behavior when the operations list is empty', function (): void {
    $layout = new Layout(
        handle: 'no_ops_handle',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('ComponentA', 'a', [], []),
            new Place('ComponentB', 'b', [], []),
        ]],
        operations: [],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layout]),
    );

    expect($result['no_ops_handle']->slots['main'])->toHaveCount(2)
        ->and($result['no_ops_handle']->slots['main'][0]->component)->toBe('ComponentA')
        ->and($result['no_ops_handle']->slots['main'][1]->component)->toBe('ComponentB');
});

// =============================================================================
// Task 026: Handle inheritance resolution
// =============================================================================

it('resolves a single-level inheritance chain by appending parent placements before child placements', function (): void {
    $parentLayout = new Layout(
        handle: 'parent.handle',
        extends: null,
        context: [],
        slots: ['main' => [new Place('ParentComponent', 'parent-item', [], [])]],
    );
    $childLayout = new Layout(
        handle: 'child.handle',
        extends: null,
        inherits: 'parent.handle',
        context: [],
        slots: ['main' => [new Place('ChildComponent', 'child-item', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$parentLayout, $childLayout]),
    );

    expect($result)->toHaveKey('child.handle')
        ->and($result['child.handle']->slots['main'])->toHaveCount(2)
        ->and($result['child.handle']->slots['main'][0]->component)->toBe('ParentComponent')
        ->and($result['child.handle']->slots['main'][1]->component)->toBe('ChildComponent');
});

it('resolves a multi-level inheritance chain in ancestor-first order', function (): void {
    $grandparentLayout = new Layout(
        handle: 'grandparent.handle',
        extends: null,
        context: [],
        slots: ['main' => [new Place('GrandparentComponent', 'grandparent-item', [], [])]],
    );
    $parentLayout = new Layout(
        handle: 'parent.handle',
        extends: null,
        inherits: 'grandparent.handle',
        context: [],
        slots: ['main' => [new Place('ParentComponent', 'parent-item', [], [])]],
    );
    $childLayout = new Layout(
        handle: 'child.handle',
        extends: null,
        inherits: 'parent.handle',
        context: [],
        slots: ['main' => [new Place('ChildComponent', 'child-item', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$grandparentLayout, $parentLayout, $childLayout]),
    );

    expect($result)->toHaveKey('child.handle')
        ->and($result['child.handle']->slots['main'])->toHaveCount(3)
        ->and($result['child.handle']->slots['main'][0]->component)->toBe('GrandparentComponent')
        ->and($result['child.handle']->slots['main'][1]->component)->toBe('ParentComponent')
        ->and($result['child.handle']->slots['main'][2]->component)->toBe('ChildComponent');
});

it('merges parent context providers ahead of child context providers', function (): void {
    $parentProvide = new \Markommerce\Layout\Provide('ParentToken', 'ParentProvider', []);
    $childProvide = new \Markommerce\Layout\Provide('ChildToken', 'ChildProvider', []);

    $parentLayout = new Layout(
        handle: 'context.parent',
        extends: null,
        context: [$parentProvide],
        slots: [],
    );
    $childLayout = new Layout(
        handle: 'context.child',
        extends: null,
        inherits: 'context.parent',
        context: [$childProvide],
        slots: [],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$parentLayout, $childLayout]),
    );

    expect($result['context.child']->context)->toHaveCount(2)
        ->and($result['context.child']->context[0]->token)->toBe('ParentToken')
        ->and($result['context.child']->context[1]->token)->toBe('ChildToken');
});

it('allows the child to remove a placement contributed by the parent via Remove operation', function (): void {
    $parentLayout = new Layout(
        handle: 'parent.remove',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('ParentComponent', 'parent-item', [], []),
            new Place('AnotherComponent', 'another-item', [], []),
        ]],
    );
    $childLayout = new Layout(
        handle: 'child.remove',
        extends: null,
        inherits: 'parent.remove',
        context: [],
        slots: [],
        operations: [new \Markommerce\Layout\Operation\Remove('parent-item')],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$parentLayout, $childLayout]),
    );

    expect($result['child.remove']->slots['main'])->toHaveCount(1)
        ->and($result['child.remove']->slots['main'][0]->component)->toBe('AnotherComponent');
});

it('allows the child to wrap a placement contributed by the parent via WrapWith operation', function (): void {
    $parentLayout = new Layout(
        handle: 'parent.wrap',
        extends: null,
        context: [],
        slots: ['main' => [new Place('ParentComponent', 'parent-item', [], [])]],
    );
    $childLayout = new Layout(
        handle: 'child.wrap',
        extends: null,
        inherits: 'parent.wrap',
        context: [],
        slots: [],
        operations: [new \Markommerce\Layout\Operation\WrapWith('parent-item', 'WrapperDecorator')],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$parentLayout, $childLayout]),
    );

    expect($result['child.wrap']->slots['main'])->toHaveCount(1)
        ->and($result['child.wrap']->slots['main'][0]->component)->toBe('ParentComponent')
        ->and($result['child.wrap']->slots['main'][0]->decorators)->toBe(['WrapperDecorator']);
});

it('throws CircularInheritanceException when a chain points back to itself', function (): void {
    // A inherits B, B inherits A — direct cycle
    $layoutA = new Layout(
        handle: 'cycle.a',
        extends: null,
        inherits: 'cycle.b',
        context: [],
        slots: [],
    );
    $layoutB = new Layout(
        handle: 'cycle.b',
        extends: null,
        inherits: 'cycle.a',
        context: [],
        slots: [],
    );

    expect(fn() => (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layoutA, $layoutB]),
    ))->toThrow(\Markommerce\Layout\Exception\CircularInheritanceException::class);
});

it('throws CircularInheritanceException for a multi-step cycle A → B → C → A', function (): void {
    $layoutA = new Layout(
        handle: 'multi.a',
        extends: null,
        inherits: 'multi.b',
        context: [],
        slots: [],
    );
    $layoutB = new Layout(
        handle: 'multi.b',
        extends: null,
        inherits: 'multi.c',
        context: [],
        slots: [],
    );
    $layoutC = new Layout(
        handle: 'multi.c',
        extends: null,
        inherits: 'multi.a',
        context: [],
        slots: [],
    );

    expect(fn() => (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$layoutA, $layoutB, $layoutC]),
    ))->toThrow(\Markommerce\Layout\Exception\CircularInheritanceException::class);
});

it('throws UnknownParentHandleException when inherits references a handle that is not defined', function (): void {
    $childLayout = new Layout(
        handle: 'orphan.child',
        extends: null,
        inherits: 'nonexistent.parent',
        context: [],
        slots: [],
    );

    expect(fn() => (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$childLayout]),
    ))->toThrow(\Markommerce\Layout\Exception\UnknownParentHandleException::class);
});

it('throws DuplicateContextTokenException when parent and child Provide entries declare the same token', function (): void {
    $sharedProvide = new \Markommerce\Layout\Provide('SharedToken', 'SomeProvider', []);

    $parentLayout = new Layout(
        handle: 'dup.parent',
        extends: null,
        context: [$sharedProvide],
        slots: [],
    );
    $childLayout = new Layout(
        handle: 'dup.child',
        extends: null,
        inherits: 'dup.parent',
        context: [$sharedProvide],
        slots: [],
    );

    expect(fn() => (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$parentLayout, $childLayout]),
    ))->toThrow(\Markommerce\Layout\Exception\DuplicateContextTokenException::class);
});

it('composes extends and inherits on the same layout: shell then parent then own slots, in order', function (): void {
    // The parent handle has its own placements.
    $parentLayout = new Layout(
        handle: 'compose.parent',
        extends: null,
        context: [],
        slots: ['main' => [new Place('ParentComponent', 'parent-item', [], [])]],
    );

    // The child extends a shell (BaseLayoutDefinition: provides 'header' in 'main'),
    // inherits the parent handle (which adds 'parent-item'),
    // and adds its own slot entry ('child-item').
    $childLayout = new Layout(
        handle: 'compose.child',
        extends: BaseLayoutDefinition::class,
        inherits: 'compose.parent',
        context: [],
        slots: ['main' => [new Place('ChildComponent', 'child-item', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDiscoveryResult([$parentLayout, $childLayout]),
    );

    // Expected order: shell ('header') → parent ('parent-item') → own ('child-item')
    expect($result['compose.child']->slots['main'])->toHaveCount(3)
        ->and($result['compose.child']->slots['main'][0]->component)->toBe('BaseHeader')
        ->and($result['compose.child']->slots['main'][1]->component)->toBe('ParentComponent')
        ->and($result['compose.child']->slots['main'][2]->component)->toBe('ChildComponent');
});
