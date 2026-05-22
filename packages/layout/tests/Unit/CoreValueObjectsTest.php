<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;

it('constructs a Layout with a handle, extends target, context list and slots', function (): void {
    $provide = new Provide('SomeToken', 'SomeProvider', []);
    $place = new Place('SomeComponent', null, [], []);
    $layout = new Layout(
        handle: 'some-handle',
        extends: LayoutDefinition::class,
        context: [$provide],
        slots: ['main' => [$place]],
    );

    expect($layout->handle)->toBe('some-handle')
        ->and($layout->extends)->toBe(LayoutDefinition::class)
        ->and($layout->context)->toBe([$provide])
        ->and($layout->slots)->toBe(['main' => [$place]]);
});

it('accepts a controller-action pair as a layout handle', function (): void {
    $layout = new Layout(
        handle: ['SomeController', 'show'],
        extends: null,
        context: [],
        slots: [],
    );

    expect($layout->handle)->toBe(['SomeController', 'show']);
});

it('allows a Layout with a null handle for a base layout used only via extends', function (): void {
    $layout = new Layout(
        handle: null,
        extends: null,
        context: [],
        slots: [],
    );

    expect($layout->handle)->toBeNull();
});

it('carries an optional template name on a Layout', function (): void {
    $layout = new Layout(
        handle: null,
        extends: null,
        context: [],
        slots: [],
        template: 'one_column',
    );

    expect($layout->template)->toBe('one_column');
});

it('constructs a Place with component, name, props and sub-slots', function (): void {
    $subPlace = new Place('SubComponent', null, [], []);
    $place = new Place(
        component: 'MyComponent',
        name: 'catalog.product_card',
        props: ['sku' => 'abc'],
        slots: ['inner' => [$subPlace]],
    );

    expect($place->component)->toBe('MyComponent')
        ->and($place->name)->toBe('catalog.product_card')
        ->and($place->props)->toBe(['sku' => 'abc'])
        ->and($place->slots)->toBe(['inner' => [$subPlace]]);
});

it('allows a Place with a null name', function (): void {
    $place = new Place('MyComponent', null, [], []);

    expect($place->name)->toBeNull();
});

it('constructs a keyed slot as a list of placements', function (): void {
    $place1 = new Place('ComponentA', null, [], []);
    $place2 = new Place('ComponentB', null, [], []);

    $layout = new Layout(
        handle: null,
        extends: null,
        context: [],
        slots: ['main' => [$place1, $place2]],
    );

    expect($layout->slots['main'])->toBe([$place1, $place2]);
});

it('constructs a repeat slot via Slot::repeat with data key, yields type and iteration token', function (): void {
    $child = new Place('ProductCard', null, [], []);
    $slot = Slot::repeat(
        dataKey: 'products',
        yields: 'ProductItemClass',
        as: 'ProductTokenClass',
        children: [$child],
    );

    expect($slot)->toBeInstanceOf(Slot::class);
});

it('exposes the yields type and iteration token on a repeat slot', function (): void {
    $child = new Place('ProductCard', null, [], []);
    $slot = Slot::repeat(
        dataKey: 'products',
        yields: 'ProductItemClass',
        as: 'ProductTokenClass',
        children: [$child],
    );

    expect($slot->dataKey)->toBe('products')
        ->and($slot->yields)->toBe('ProductItemClass')
        ->and($slot->as)->toBe('ProductTokenClass')
        ->and($slot->children)->toBe([$child]);
});

it('constructs a Provide with a token class, provider class and props', function (): void {
    $provide = new Provide(
        token: 'SomeToken',
        provider: 'SomeProvider',
        props: ['key' => 'value'],
    );

    expect($provide->token)->toBe('SomeToken')
        ->and($provide->provider)->toBe('SomeProvider')
        ->and($provide->props)->toBe(['key' => 'value']);
});

it('defines a LayoutDefinition interface with a static define method', function (): void {
    $reflection = new ReflectionClass(LayoutDefinition::class);

    expect($reflection->isInterface())->toBeTrue();

    $method = $reflection->getMethod('define');
    expect($method->isStatic())->toBeTrue()
        ->and($method->isPublic())->toBeTrue();
});

it('defines a ContextProvider interface', function (): void {
    $reflection = new ReflectionClass(ContextProvider::class);

    expect($reflection->isInterface())->toBeTrue();

    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    expect(count($methods))->toBeGreaterThanOrEqual(1);
});
