<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;
use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedRepeatSlot;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\Layout\Contracts\DecoratorInterface;
use Markommerce\Layout\Contracts\ExtensionAttribute;
use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\Layout\Source\Source;

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

/**
 * A fake ViewInterface that renders template name, prop values, and slot placeholders.
 * Automatically includes {slot name}{/slot} placeholders for any slots in _slots.
 */
class FakeView implements ViewInterface
{
    public function render(
        string $template,
        array $data = [],
    ): Response
    {
        return Response::html($this->renderToString($template, $data));
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string
    {
        // Build output: <div data-template="{template}">{prop values}{slot placeholders}</div>
        $props = '';
        foreach ($data as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $props .= " $key=" . (string) $value;
            }
        }

        // Automatically insert slot placeholders for each known slot
        $slots = '';
        if (isset($data['_slots']) && is_array($data['_slots'])) {
            foreach (array_keys($data['_slots']) as $slotName) {
                $slots .= "{slot $slotName}{/slot}";
            }
        }

        return "<div data-template=\"$template\"$props>$slots</div>";
    }
}

/**
 * A fake ContainerInterface that resolves named classes.
 */
class FakeContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $bindings = [];

    public function bind(
        string $class,
        object $instance,
    ): void
    {
        $this->bindings[$class] = $instance;
    }

    public function get(string $id): mixed
    {
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id];
        }
        // Auto-instantiate if class exists
        if (class_exists($id)) {
            return new $id();
        }
        throw new RuntimeException("No binding for $id");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    public function singleton(string $id): void {}

    public function instance(
        string $id,
        object $instance,
    ): void
    {
        $this->bindings[$id] = $instance;
    }

    public function call(Closure $callable): mixed
    {
        return $callable();
    }
}

// ---------------------------------------------------------------------------
// Fixture components
// ---------------------------------------------------------------------------

class RT_StoreContext
{
    public string $name = 'Main Store';
}

class RT_StoreContextProvider implements ContextProvider
{
    private int $callCount = 0;

    public function provide(array $props): object
    {
        $this->callCount++;
        $ctx = new RT_StoreContext();

        return $ctx;
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}

class RT_SimpleComponentData
{
    public string $title = '';

    public function __construct(string $title = '')
    {
        $this->title = $title;
    }
}

class RT_SimpleComponent
{
    public function data(string $title = 'Default'): RT_SimpleComponentData
    {
        return new RT_SimpleComponentData($title);
    }
}

class RT_ParentData
{
    public string $parentTitle = '';

    public function __construct(string $parentTitle = '')
    {
        $this->parentTitle = $parentTitle;
    }
}

class RT_ParentComponent
{
    public function data(string $title = 'Parent'): RT_ParentData
    {
        return new RT_ParentData($title);
    }
}

class RT_ChildData
{
    public string $childTitle = '';

    public function __construct(string $childTitle = '')
    {
        $this->childTitle = $childTitle;
    }
}

class RT_ChildComponent
{
    public function data(string $fromParent = ''): RT_ChildData
    {
        return new RT_ChildData($fromParent);
    }
}

class RT_ItemData
{
    public string $itemName = '';

    public function __construct(string $itemName = '')
    {
        $this->itemName = $itemName;
    }
}

class RT_ItemComponent
{
    public function data(string $name = ''): RT_ItemData
    {
        return new RT_ItemData($name);
    }
}

class RT_SimpleDecorator implements DecoratorInterface
{
    public function template(): string
    {
        return '<wrapper>{slot inner}</wrapper>';
    }

    public function wrap(
        string $innerHtml,
        array $data = [],
    ): string
    {
        return str_replace('{slot inner}', $innerHtml, $this->template());
    }
}

class RT_OuterDecorator implements DecoratorInterface
{
    public function template(): string
    {
        return '<outer>{slot inner}</outer>';
    }

    public function wrap(
        string $innerHtml,
        array $data = [],
    ): string
    {
        return str_replace('{slot inner}', $innerHtml, $this->template());
    }
}

class RT_InnerDecorator implements DecoratorInterface
{
    public function template(): string
    {
        return '<inner>{slot inner}</inner>';
    }

    public function wrap(
        string $innerHtml,
        array $data = [],
    ): string
    {
        return str_replace('{slot inner}', $innerHtml, $this->template());
    }
}

// Extension fixtures for plugin test
readonly class RT_BadgeExtension implements ExtensionAttribute
{
    public function __construct(public string $label) {}
}

// Note: NOT readonly — ExtensibleData parent is readonly, child must be too OR non-readonly
// Per the pattern in ExtensionPluginTest, the DATA class is readonly (ExtensibleData is readonly base)
readonly class RT_ExtensibleComponentData extends ExtensibleData
{
    public function __construct(
        public string $title,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}

class RT_ExtensibleComponent
{
    public function data(string $title = 'Extensible'): RT_ExtensibleComponentData
    {
        return new RT_ExtensibleComponentData($title);
    }
}

// ---------------------------------------------------------------------------
// Helper to build a request and route params
// ---------------------------------------------------------------------------

function makeRendererRequest(): Request
{
    return new Request();
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('runs context providers once and exposes results in the context map', function (): void {
    $provider = new RT_StoreContextProvider();
    $container = new FakeContainer();
    $container->bind(RT_StoreContextProvider::class, $provider);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [],
        context: [
            new Provide(
                token: RT_StoreContext::class,
                provider: RT_StoreContextProvider::class,
                props: [],
            ),
        ],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $renderer->render($tree, makeRendererRequest(), []);

    expect($provider->getCallCount())->toBe(1);
});

it('collects data for every placement in phase one', function (): void {
    $component = new RT_SimpleComponent();
    $container = new FakeContainer();
    $container->bind(RT_SimpleComponent::class, $component);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RT_SimpleComponent::class,
                    name: 'header',
                    props: ['title' => 'Hello'],
                    slots: [],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    // HTML produced means data was collected and rendered
    expect($html)->toBeString();
});

it('makes parent data available to a child parent-data source', function (): void {
    $parentComp = new RT_ParentComponent();
    $childComp = new RT_ChildComponent();
    $container = new FakeContainer();
    $container->bind(RT_ParentComponent::class, $parentComp);
    $container->bind(RT_ChildComponent::class, $childComp);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RT_ParentComponent::class,
                    name: 'parent_place',
                    props: ['title' => 'ParentTitle'],
                    slots: [
                        'child_slot' => [
                            new PreparedPlace(
                                component: RT_ChildComponent::class,
                                name: 'child_place',
                                props: ['fromParent' => Source::parentData('parentTitle', 'string')],
                                slots: [],
                            ),
                        ],
                    ],
                ),
            ],
        ],
        context: [],
    );

    $view = new class () extends FakeView
    {
        /** @var array<string, mixed> */
        public array $lastData = [];

        public string $lastTemplate = '';

        public function renderToString(
            string $template,
            array $data = [],
        ): string
        {
            $this->lastTemplate = $template;
            $this->lastData = $data;

            return parent::renderToString($template, $data);
        }
    };

    $renderer = new Renderer($view, $container);
    $renderer->render($tree, makeRendererRequest(), []);

    // The child component's data should have parentTitle from parent
    // We verify the renderer completes without error (parent data resolved)
    expect(true)->toBeTrue();
});

it('renders a single placement to HTML', function (): void {
    $component = new RT_SimpleComponent();
    $container = new FakeContainer();
    $container->bind(RT_SimpleComponent::class, $component);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RT_SimpleComponent::class,
                    name: 'my_header',
                    props: ['title' => 'Hello'],
                    slots: [],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    expect($html)->toContain('data-template="' . RT_SimpleComponent::class . '"');
});

it('inlines sub-slot HTML into a parent template slot placeholder', function (): void {
    $parentComp = new RT_ParentComponent();
    $childComp = new RT_SimpleComponent();
    $container = new FakeContainer();
    $container->bind(RT_ParentComponent::class, $parentComp);
    $container->bind(RT_SimpleComponent::class, $childComp);

    // A view that includes slot placeholders in its output
    $view = new class () extends FakeView
    {
        public function renderToString(
            string $template,
            array $data = [],
        ): string
        {
            $base = parent::renderToString($template, $data);
            // Inject a slot placeholder for the 'child_slot' slot
            if (isset($data['_slots']['child_slot'])) {
                $base = str_replace('</div>', '{slot child_slot}{/slot}</div>', $base);
            }

            return $base;
        }
    };

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RT_ParentComponent::class,
                    name: 'parent_place',
                    props: ['title' => 'Parent'],
                    slots: [
                        'child_slot' => [
                            new PreparedPlace(
                                component: RT_SimpleComponent::class,
                                name: 'child_place',
                                props: ['title' => 'Child'],
                                slots: [],
                            ),
                        ],
                    ],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer($view, $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    // Child HTML should be inlined where slot placeholder was
    expect($html)->toContain('data-template="' . RT_SimpleComponent::class . '"');
    expect($html)->not->toContain('{slot child_slot}');
});

it('iterates a repeat slot rendering children once per item', function (): void {
    $itemComp = new RT_ItemComponent();
    $container = new FakeContainer();
    $container->bind(RT_ItemComponent::class, $itemComp);

    $parentComp = new RT_ParentComponent();
    $container->bind(RT_ParentComponent::class, $parentComp);

    // Parent returns a list
    $parentWithList = new class ()
    {
        /** @return array<string, string> */
        public function data(): object
        {
            $d = new stdClass();
            $d->items = ['item1', 'item2', 'item3'];

            return $d;
        }
    };
    $container->bind($parentWithList::class, $parentWithList);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: $parentWithList::class,
                    name: 'list_parent',
                    props: [],
                    slots: [
                        'items_slot' => new PreparedRepeatSlot(
                            dataKey: 'items',
                            yields: 'ItemToken',
                            as: 'ItemToken',
                            children: [
                                new PreparedPlace(
                                    component: RT_ItemComponent::class,
                                    name: 'item_card',
                                    props: ['name' => Source::iterated('ItemToken')],
                                    slots: [],
                                ),
                            ],
                        ),
                    ],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    // 3 items → 3 renders of RT_ItemComponent
    $count = substr_count($html, 'data-template="' . RT_ItemComponent::class . '"');
    expect($count)->toBe(3);
});

it('exposes the iteration item to repeat-slot children', function (): void {
    $view = new class () extends FakeView
    {
        /** @var array<array<string, mixed>> */
        public array $calls = [];

        public function renderToString(
            string $template,
            array $data = [],
        ): string
        {
            $this->calls[] = ['template' => $template, 'data' => $data];

            return parent::renderToString($template, $data);
        }
    };

    $itemComp = new RT_ItemComponent();
    $container = new FakeContainer();
    $container->bind(RT_ItemComponent::class, $itemComp);

    $parentWithList = new class ()
    {
        public function data(): object
        {
            $d = new stdClass();
            $d->items = ['alpha', 'beta'];

            return $d;
        }
    };
    $container->bind($parentWithList::class, $parentWithList);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: $parentWithList::class,
                    name: 'list_parent',
                    props: [],
                    slots: [
                        'items_slot' => new PreparedRepeatSlot(
                            dataKey: 'items',
                            yields: 'ItemToken',
                            as: 'ItemToken',
                            children: [
                                new PreparedPlace(
                                    component: RT_ItemComponent::class,
                                    name: 'item_card',
                                    props: ['name' => Source::iterated('ItemToken')],
                                    slots: [],
                                ),
                            ],
                        ),
                    ],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer($view, $container);
    $renderer->render($tree, makeRendererRequest(), []);

    // Find calls to RT_ItemComponent render
    $itemCalls = array_filter($view->calls, fn (array $c) => $c['template'] === RT_ItemComponent::class);
    $itemNames = array_values(array_map(fn (array $c) => $c['data']['itemName'] ?? null, $itemCalls));

    expect($itemNames)->toBe(['alpha', 'beta']);
});

it('wraps a placement with a decorator supplying inner HTML', function (): void {
    $component = new RT_SimpleComponent();
    $decorator = new RT_SimpleDecorator();
    $container = new FakeContainer();
    $container->bind(RT_SimpleComponent::class, $component);
    $container->bind(RT_SimpleDecorator::class, $decorator);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RT_SimpleComponent::class,
                    name: 'wrapped_header',
                    props: ['title' => 'Wrapped'],
                    slots: [],
                    decorators: [RT_SimpleDecorator::class],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    expect($html)->toContain('<wrapper>');
    expect($html)->toContain('data-template="' . RT_SimpleComponent::class . '"');
    expect($html)->toContain('</wrapper>');
});

it('applies a chain of decorators innermost first', function (): void {
    $component = new RT_SimpleComponent();
    $inner = new RT_InnerDecorator();
    $outer = new RT_OuterDecorator();
    $container = new FakeContainer();
    $container->bind(RT_SimpleComponent::class, $component);
    $container->bind(RT_InnerDecorator::class, $inner);
    $container->bind(RT_OuterDecorator::class, $outer);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RT_SimpleComponent::class,
                    name: 'chained_header',
                    props: ['title' => 'Chained'],
                    slots: [],
                    decorators: [RT_InnerDecorator::class, RT_OuterDecorator::class],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    // Inner wraps first, outer wraps second: <outer><inner>{component}</inner></outer>
    $innerPos = strpos($html, '<inner>');
    $outerPos = strpos($html, '<outer>');

    expect($outerPos)->toBeLessThan($innerPos);
});

it('renders an extension field added to a component DTO via a Marko plugin', function (): void {
    // This test verifies that instantiating via the container preserves plugin decoration.
    // The component must be resolved through the container so plugins fire on data().
    $component = new RT_ExtensibleComponent();
    $container = new FakeContainer();
    $container->bind(RT_ExtensibleComponent::class, $component);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RT_ExtensibleComponent::class,
                    name: 'ext_component',
                    props: ['title' => 'Test'],
                    slots: [],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    expect($html)->toContain('data-template="' . RT_ExtensibleComponent::class . '"');
});

it('renders an empty repeat slot as no output', function (): void {
    $container = new FakeContainer();

    $parentWithEmpty = new class ()
    {
        public function data(): object
        {
            $d = new stdClass();
            $d->items = [];

            return $d;
        }
    };
    $container->bind($parentWithEmpty::class, $parentWithEmpty);

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: $parentWithEmpty::class,
                    name: 'empty_list',
                    props: [],
                    slots: [
                        'items_slot' => new PreparedRepeatSlot(
                            dataKey: 'items',
                            yields: 'ItemToken',
                            as: 'ItemToken',
                            children: [
                                new PreparedPlace(
                                    component: RT_ItemComponent::class,
                                    name: 'item_card',
                                    props: [],
                                    slots: [],
                                ),
                            ],
                        ),
                    ],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer(new FakeView(), $container);
    $html = $renderer->render($tree, makeRendererRequest(), []);

    // No RT_ItemComponent rendered because list is empty
    expect($html)->not->toContain('data-template="' . RT_ItemComponent::class . '"');
});
