<?php

declare(strict_types=1);

use Markommerce\Layout\Attributes\IteratesOver;
use Markommerce\Layout\Compiler\ResolvedLayout;
use Markommerce\Layout\Compiler\ResolvedPlace;
use Markommerce\Layout\Compiler\ResolvedRepeatSlot;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Exceptions\DanglingAnchorException;
use Markommerce\Layout\Exceptions\DuplicateNameException;
use Markommerce\Layout\Exceptions\MissingDataKeyException;
use Markommerce\Layout\Exceptions\MissingPropException;
use Markommerce\Layout\Exceptions\RepeatTypeMismatchException;
use Markommerce\Layout\Exceptions\TypeMismatchException;
use Markommerce\Layout\Exceptions\UnknownContextException;
use Markommerce\Layout\Exceptions\UnknownIterationException;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Source\ContextSource;
use Markommerce\Layout\Source\IteratedSource;
use Markommerce\Layout\Source\ParentDataSource;
use Markommerce\Layout\Source\RouteSource;

// =============================================================================
// Fixture DTOs and Components
// =============================================================================

class SimpleDto
{
    public function __construct(
        public string $title,
        public string $subtitle = '',
    ) {}
}

class SimpleComponent
{
    public function data(
        string $title,
        string $subtitle = '',
    ): SimpleDto
    {
        return new SimpleDto($title, $subtitle);
    }
}

class ProductItemDto
{
    public string $name = '';
}

#[IteratesOver(itemType: ProductItemDto::class)]
class ProductToken {}

class ProductListDto
{
    /**
     * @var list<ProductItemDto>
     */
    public array $items = [];
}

class ProductListComponent
{
    public function data(string $title): ProductListDto
    {
        return new ProductListDto();
    }
}

class ProductCardDto
{
    public string $name = '';
}

class ProductCardComponent
{
    public function data(string $name): ProductCardDto
    {
        return new ProductCardDto();
    }
}

// Component whose data() doesn't return a DTO (returns array)
class BadReturnComponent
{
    /** @return array<string, mixed> */
    public function data(string $title): array
    {
        return ['title' => $title];
    }
}

// Component with an items property typed as a wrong element type
class WrongItemTypeDto
{
    /**
     * @var list<SimpleDto>
     */
    public array $items = [];
}

class WrongItemTypeComponent
{
    public function data(): WrongItemTypeDto
    {
        return new WrongItemTypeDto();
    }
}

// =============================================================================
// Helpers
// =============================================================================

function makeLayout(
    string $handleKey = 'test_handle',
    array $slots = [],
    array $context = [],
): ResolvedLayout {
    return new ResolvedLayout(
        handle: $handleKey,
        handleKey: $handleKey,
        template: 'layouts/test.latte',
        slots: $slots,
        context: $context,
    );
}

function makePlacement(
    string $component,
    ?string $name,
    array $props = [],
    array $slots = [],
): ResolvedPlace {
    return new ResolvedPlace(
        component: $component,
        name: $name,
        props: $props,
        slots: $slots,
    );
}

// =============================================================================
// Tests
// =============================================================================

it('passes a fully valid resolved tree without error', function (): void {
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'test.placement', [
                    'title' => new RouteSource('id', 'string'),
                ]),
            ],
        ],
        context: [new Provide('myToken', SimpleComponent::class, [])],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))->not->toThrow(Throwable::class);
});

it('throws DuplicateNameException when two placements share a name', function (): void {
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'shared.name', ['title' => new RouteSource('id', 'string')]),
                makePlacement(SimpleComponent::class, 'shared.name', ['title' => new RouteSource('id', 'string')]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(DuplicateNameException::class);
});

it('throws an error when a placement name violates the name format', function (): void {
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'InvalidName', ['title' => new RouteSource('id', 'string')]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(InvalidArgumentException::class);
});

it('throws UnknownContextException when a context source has no matching Provide', function (): void {
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'test.placement', [
                    'title' => new ContextSource('unknownToken', null),
                ]),
            ],
        ],
        context: [], // No Provide for 'unknownToken'
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(UnknownContextException::class);
});

it('throws UnknownIterationException when an iterated source has no enclosing repeat slot', function (): void {
    // IteratedSource used at top level (not inside a repeat slot)
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'test.placement', [
                    'title' => new IteratedSource('myToken', null),
                ]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(UnknownIterationException::class);
});

it('throws MissingDataKeyException when a repeat slot key is absent from the parent data DTO', function (): void {
    // ProductListDto has 'items' property, but we'll use 'nonexistent' as dataKey
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(
                    ProductListComponent::class,
                    'product.list',
                    ['title' => new RouteSource('id', 'string')],
                    [
                        'items' => new ResolvedRepeatSlot(
                            dataKey: 'nonexistent', // Not a property on ProductListDto
                        yields: ProductItemDto::class,
                            as: 'product',
                            children: [],
                        ),
                    ]
                ),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(MissingDataKeyException::class);
});

it('throws RepeatTypeMismatchException when the repeat item type does not match the yields type', function (): void {
    // WrongItemTypeDto has items typed as list<SimpleDto>, but yields ProductItemDto
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(WrongItemTypeComponent::class, 'product.list', [], [
                    'items' => new ResolvedRepeatSlot(
                        dataKey: 'items',
                        yields: ProductItemDto::class, // mismatch: items are list<SimpleDto>
                        as: 'product',
                        children: [],
                    ),
                ]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(RepeatTypeMismatchException::class);
});

it('throws TypeMismatchException when a source type is not assignable to the component prop', function (): void {
    // SimpleComponent::data() expects string $title, but RouteSource with 'int' as doesn't match
    // Actually, for type mismatch we need a source that resolves to a wrong type.
    // ContextSource resolves to a class type; if the prop is string, that's a mismatch.
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'test.placement', [
                    'title' => new RouteSource('id', 'int'), // resolves to int, but prop expects string
                ]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(TypeMismatchException::class);
});

it('throws MissingPropException when a required component prop has no source', function (): void {
    // SimpleComponent::data() requires $title (no default), but no prop provided
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'test.placement', [
                    // 'title' is missing — it's required
                ]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(MissingPropException::class);
});

it('throws DanglingAnchorException when a wrap marker references a missing placement', function (): void {
    // A placement with a decorator that wraps 'nonexistent.name'
    $layout = makeLayout(
        slots: [
            'main' => [
                new ResolvedPlace(
                    component: SimpleComponent::class,
                    name: 'test.placement',
                    props: ['title' => new RouteSource('id', 'string')],
                    slots: [],
                    decorators: ['nonexistent.name'],
                ),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(DanglingAnchorException::class);
});

it('rejects a parentData source on a top-level placement with no parent', function (): void {
    // ParentDataSource used on a top-level placement (no enclosing parent placement)
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'test.placement', [
                    'title' => new ParentDataSource('someKey', 'string'),
                ]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(UnknownContextException::class);
});

it('throws MissingDataKeyException when a parentData key is absent from the parent data DTO', function (): void {
    // ProductCardComponent has ProductCardDto with 'name' property
    // Nested placement uses parentData('nonexistent')
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(
                    ProductCardComponent::class,
                    'product.card',
                    ['name' => new RouteSource('id', 'string')],
                    [
                        'detail' => [
                            makePlacement(SimpleComponent::class, 'product.detail', [
                                'title' => new ParentDataSource('nonexistentKey', 'string'),
                            ]),
                        ],
                    ]
                ),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(MissingDataKeyException::class);
});

it('rejects a component whose data method does not return a concrete DTO class', function (): void {
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(BadReturnComponent::class, 'test.placement', [
                    'title' => new RouteSource('id', 'string'),
                ]),
            ],
        ],
    );

    $validator = new ValidationPhase();
    expect(fn () => $validator->validate(['test_handle' => $layout]))
        ->toThrow(TypeMismatchException::class);
});

it('includes the placement chain in the context of every validation error', function (): void {
    $layout = makeLayout(
        slots: [
            'main' => [
                makePlacement(SimpleComponent::class, 'shared.name', ['title' => new RouteSource('id', 'string')]),
                makePlacement(SimpleComponent::class, 'shared.name', ['title' => new RouteSource('id', 'string')]),
            ],
        ],
    );

    $validator = new ValidationPhase();

    try {
        $validator->validate(['test_handle' => $layout]);
        expect(false)->toBeTrue('Expected DuplicateNameException to be thrown');
    } catch (DuplicateNameException $e) {
        expect($e->getContext())->toContain('shared.name');
    }
});
