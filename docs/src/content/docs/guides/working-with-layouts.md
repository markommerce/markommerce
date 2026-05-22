---
title: Working with Layouts
description: Step-by-step guide to defining layouts, wiring context providers, using repeat slots, and extending layouts from another module using the markommerce/layout system.
---

The `markommerce/layout` system gives you a placement-agnostic component tree: components declare no slots or handles — placement is described entirely in layout definition files. Trees are compile-validated, and third-party modules extend layouts through a closed vocabulary of typed operations. This keeps component code free of layout concerns and makes every extension auditable and reversible.

Throughout this guide the `markommerce/layout-demo` package is used as the worked example. All class names referenced here exist in that package.

## Defining a Layout

A layout definition file lives at `{module}/layout/{name}.php` and returns a `Layout` value object. The `handle` property ties the layout to a specific controller action — the middleware matches incoming requests to that action and selects the correct compiled tree.

```php title="packages/layout-demo/layout/layout_demo.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;
use Markommerce\LayoutDemo\Component\GalleryComponent;
use Markommerce\LayoutDemo\Component\GalleryFooterComponent;
use Markommerce\LayoutDemo\Component\GalleryHeaderComponent;
use Markommerce\LayoutDemo\Component\ItemComponent;
use Markommerce\LayoutDemo\Context\GalleryContextProvider;
use Markommerce\LayoutDemo\Context\GalleryToken;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;
use Markommerce\LayoutDemo\Entity\Item;
use Markommerce\LayoutDemo\Iteration\ItemIteration;
use Markommerce\LayoutDemo\Service\LabelFormatterInterface;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

return new Layout(
    handle: [LayoutDemoController::class, 'show'],
    extends: OneColumnLayout::class,
    context: [
        new Provide(
            token: GalleryToken::class,
            provider: GalleryContextProvider::class,
            props: ['id' => Source::route('id', 'int')],
        ),
    ],
    slots: [
        'content' => [
            new Place(
                component: GalleryHeaderComponent::class,
                name: 'layout_demo.gallery_header',
                props: ['gallery' => Source::context(GalleryToken::class)],
                slots: [],
                template: 'layout-demo::gallery-header',
            ),
            new Place(
                component: GalleryComponent::class,
                name: 'layout_demo.gallery',
                props: [
                    'gallery' => Source::context(GalleryToken::class),
                    'page' => Source::query('page', 1, 'int'),
                ],
                slots: [
                    'items' => Slot::repeat(
                        dataKey: 'items',
                        yields: Item::class,
                        as: ItemIteration::class,
                        children: [
                            new Place(
                                component: ItemComponent::class,
                                name: 'layout_demo.item',
                                props: [
                                    'item' => Source::iterated(ItemIteration::class),
                                    'galleryTitle' => Source::parentData('title', 'string'),
                                    'labelFormatter' => Source::service(LabelFormatterInterface::class),
                                ],
                                slots: [],
                                template: 'layout-demo::item',
                            ),
                        ],
                    ),
                ],
                template: 'layout-demo::gallery',
            ),
            new Place(
                component: GalleryFooterComponent::class,
                name: 'layout_demo.gallery_footer',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-footer',
            ),
        ],
    ],
);
```

Key points:

- `handle` is a `[ClassName::class, 'methodName']` pair that matches the controller action.
- `extends` names a `LayoutDefinition` class (from a theme or base package) whose slots become the outer shell. Here `OneColumnLayout` contributes the `content` slot.
- `context` declares which context providers run before the tree renders (see the [Context providers](#context-providers) section below).
- `slots` fills named slots inherited from the parent layout with `Place` objects and `Slot` repeat groups.
- `name` on each `Place` must match the pattern `^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$`. These names are the stable identifiers that extension operations target.

## Component Data DTOs

Each component exposes a `data()` method that returns a typed DTO. The runtime resolves the props declared in the layout file and passes them as arguments. Return types are enforced at compile time.

For simple, immutable DTOs use a `readonly` class:

```php title="packages/layout-demo/src/Data/GalleryData.php"
<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Data;

use Markommerce\LayoutDemo\Entity\Item;

readonly class GalleryData
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        public string $title,
        public int $page,
        public array $items,
    ) {}
}
```

```php title="packages/layout-demo/src/Component/GalleryComponent.php"
<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Component;

use Markommerce\LayoutDemo\Data\GalleryData;
use Markommerce\LayoutDemo\Entity\GalleryEntity;

class GalleryComponent
{
    public function data(GalleryEntity $gallery, int $page): GalleryData
    {
        return new GalleryData(
            title: $gallery->title,
            page: $page,
            items: $gallery->items,
        );
    }
}
```

When a third-party module needs to attach extra data to a DTO without subclassing it, extend `ExtensibleData` instead of a plain `readonly class`. The `ExtensionBag` holds typed extension attributes keyed by class name, and plugins attach attributes via `$data->withExtension(new MyExtension(...))`.

## Context Providers

A context provider resolves a domain object — a category, a gallery, a logged-in customer — before the component tree renders. It is declared in the layout file with `Provide` and implemented by a class that satisfies `ContextProvider`.

### The interface

```php
namespace Markommerce\Layout\Contracts;

interface ContextProvider
{
    public function provide(array $props): object;
}
```

The `$props` array contains the keys declared in `Provide::$props`, resolved at render time by the `Source` descriptors you specify there. The returned object is placed in the context bag under the key `Provide::$token` and is available to any component in the layout subtree via `Source::context()`.

### Implementing a provider

```php title="packages/layout-demo/src/Context/GalleryContextProvider.php"
<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Context;

use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\LayoutDemo\Entity\GalleryEntity;
use Markommerce\LayoutDemo\Entity\Item;

class GalleryContextProvider implements ContextProvider
{
    /**
     * @param array<string, mixed> $props
     */
    public function provide(array $props): object
    {
        $id = (int) ($props['id'] ?? 1);

        $items = match ($id) {
            2 => [
                new Item(id: 1, label: 'Second Gallery Item A'),
                new Item(id: 2, label: 'Second Gallery Item B'),
            ],
            default => [
                new Item(id: 1, label: 'Alpha'),
                new Item(id: 2, label: 'Beta'),
                new Item(id: 3, label: 'Gamma'),
            ],
        };

        $title = $id === 2 ? 'Gallery Two' : 'Gallery One';

        return new GalleryEntity(title: $title, items: $items);
    }
}
```

### Wiring with `Provide`

In the layout file, declare the provider inside the `context` array:

```php
new Provide(
    token: GalleryToken::class,      // key in the context bag
    provider: GalleryContextProvider::class,  // ContextProvider FQCN
    props: ['id' => Source::route('id', 'int')],  // props forwarded to provide()
),
```

The token class (`GalleryToken`) is a plain marker class with no methods. It acts as a typed key to retrieve the resolved object from the bag:

```php title="packages/layout-demo/src/Context/GalleryToken.php"
<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Context;

class GalleryToken {}
```

Any component prop can then read the resolved object with `Source::context(GalleryToken::class)`.

## Sources

`Source` is a static factory whose methods return prop-resolution descriptors. Each descriptor tells the runtime where to read a prop's value at render time. There are six source types.

### `Source::route()`

Reads a route parameter and optionally casts it.

```php
// Read the 'id' segment as an integer
Source::route('id', 'int');
```

Allowed casts: `'string'` (default), `'int'`, `'bool'`.

### `Source::query()`

Reads a query-string parameter with an optional default value.

```php
// Read ?page=, default to 1, cast to int
Source::query('page', 1, 'int');
```

Allowed casts: `'string'` (default), `'int'`, `'bool'`.

### `Source::context()`

Reads an object from the context bag by its token class. An optional `$path` property accesses a nested property on that object.

```php
// Read the entire GalleryEntity
Source::context(GalleryToken::class);

// Read a single property on it
Source::context(GalleryToken::class, 'title');
```

### `Source::iterated()`

Inside a `Slot::repeat()` children array, reads the current iterated item from the iteration context. An optional `$path` accesses a property on the item.

```php
// Read the whole current Item
Source::iterated(ItemIteration::class);

// Read a property on the current Item
Source::iterated(ItemIteration::class, 'label');
```

### `Source::parentData()`

Reads a key from the parent component's data DTO. This is how a child component accesses data computed by its parent without re-fetching it.

```php
// Read the 'title' string from the parent component's DTO
Source::parentData('title', 'string');
```

Allowed casts: `'string'` (default), `'int'`, `'bool'`.

### `Source::service()`

Resolves a service from the dependency injection container. Use this to inject stateless services (formatters, calculators) into a component's `data()` call.

```php
// Resolve LabelFormatterInterface from the container
Source::service(LabelFormatterInterface::class);
```

## Repeat Slots

`Slot::repeat()` iterates a collection from a component's DTO and renders the `children` placements once per item. Declare it as the value of a slot name inside the parent `Place`.

```php
'items' => Slot::repeat(
    dataKey: 'items',              // key on GalleryData that holds list<Item>
    yields: Item::class,           // item type (compile-time validation)
    as: ItemIteration::class,      // iteration context token
    children: [
        new Place(
            component: ItemComponent::class,
            name: 'layout_demo.item',
            props: [
                'item' => Source::iterated(ItemIteration::class),
                'galleryTitle' => Source::parentData('title', 'string'),
                'labelFormatter' => Source::service(LabelFormatterInterface::class),
            ],
            slots: [],
            template: 'layout-demo::item',
        ),
    ],
),
```

`ItemIteration` is a marker class decorated with `#[IteratesOver(Item::class)]`. The attribute tells the compiler what item type the iteration context wraps, enabling slot-structure validation at compile time:

```php title="packages/layout-demo/src/Iteration/ItemIteration.php"
<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Iteration;

use Markommerce\Layout\Attributes\IteratesOver;
use Markommerce\LayoutDemo\Entity\Item;

#[IteratesOver(Item::class)]
class ItemIteration {}
```

## Extending a Layout

Any module can add, remove, or modify placements in an existing compiled layout without touching the original file. Extension files live at `{module}/layout/extensions/{name}.php` and return a `LayoutExtension`.

```php title="packages/layout-demo/layout/extensions/layout_demo_extension.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\InsertBefore;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\WrapWith;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\FeaturedBadgeComponent;
use Markommerce\LayoutDemo\Component\GalleryWrapperDecorator;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;

return new LayoutExtension(
    handle: [LayoutDemoController::class, 'show'],
    operations: [
        new InsertBefore(
            anchorName: 'layout_demo.gallery',
            placement: new Place(
                component: FeaturedBadgeComponent::class,
                name: 'layout_demo.featured_badge',
                props: [],
                slots: [],
                template: 'layout-demo::featured-badge',
            ),
        ),
        new WrapWith(
            name: 'layout_demo.gallery_header',
            decorator: GalleryWrapperDecorator::class,
        ),
        new MergeProps(
            name: 'layout_demo.gallery_footer',
            props: ['class' => 'highlighted'],
        ),
    ],
    priority: 0,
);
```

### Operations

| Operation | What it does |
|---|---|
| `InsertBefore` | Insert a new placement immediately before the named component |
| `InsertAfter` | Insert a new placement immediately after the named component |
| `Prepend` | Prepend a placement to a named slot |
| `Append` | Append a placement to a named slot |
| `Remove` | Remove the named placement from the tree |
| `Replace` | Replace the named placement with a different one |
| `MergeProps` | Merge additional prop bindings into a named placement |
| `ReplaceProps` | Fully replace the props of a named placement |
| `WrapWith` | Wrap a named placement with a `DecoratorInterface` implementation |

### Priority ordering

Multiple extension files targeting the same layout handle are applied in ascending `priority` order — lower numbers run first. When two extensions from different modules need a guaranteed ordering, set their `priority` values explicitly:

```php
// Runs first (inserts the badge)
return new LayoutExtension(handle: ..., operations: [...], priority: 0);

// Runs second (can reference the badge by name)
return new LayoutExtension(handle: ..., operations: [...], priority: 10);
```

## Compiling

After changing any layout definition or extension file, regenerate the compiled artifact:

```bash
vendor/bin/marko layout:compile
```

The artifact is written to `var/cache/markommerce/layouts.php`. `MarkommerceLayoutMiddleware` reads it on every request — no tree resolution happens at runtime.

In `dev` and `local` environments you can register `CompileIfStaleMiddleware`. It compares each layout source file's modification time against the artifact and recompiles automatically when any source is newer. This removes the need to run `layout:compile` manually during development.

In production, add `vendor/bin/marko layout:compile` as a deploy step so the artifact is always up to date before traffic reaches the application.

## Next Steps

- [markommerce/layout API reference](/docs/packages/layout/) — Full reference for all classes, operations, source factory methods, and middleware.
- [markommerce/layout-demo](/docs/packages/layout-demo/) — The complete worked example referenced throughout this guide.
- [markommerce/theme-blank](/docs/packages/theme-blank/) — Layout definition shells (`OneColumnLayout`, `TwoColumnsLeftLayout`, etc.) that your layouts can extend.
