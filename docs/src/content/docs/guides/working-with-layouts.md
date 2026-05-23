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
use Markommerce\LayoutDemo\Component\GalleryDeprecatedComponent;
use Markommerce\LayoutDemo\Component\GalleryFooterComponent;
use Markommerce\LayoutDemo\Component\GalleryHeaderComponent;
use Markommerce\LayoutDemo\Component\GalleryNoticeComponent;
use Markommerce\LayoutDemo\Component\GalleryPlaceholderComponent;
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
            props: ['gallery' => Source::query('gallery', 1, 'int')],
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
                    'page' => Source::route('page', 'int'),
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
            new Place(
                component: GalleryNoticeComponent::class,
                name: 'layout_demo.notice',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-notice',
            ),
            new Place(
                component: GalleryPlaceholderComponent::class,
                name: 'layout_demo.placeholder',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-placeholder',
            ),
            new Place(
                component: GalleryDeprecatedComponent::class,
                name: 'layout_demo.deprecated',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-deprecated',
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

### Prop-only components

Components that have no computation to perform — decorators, badges, labels — can omit the `data()` method entirely. The runtime then passes any props declared in the layout file directly to the Latte template as template variables. This is how `MergeProps` and `ReplaceProps` work for simple components: they modify the raw prop map before the template renders.

```php title="packages/layout-demo/src/Component/GalleryNoticeComponent.php"
<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Component;

class GalleryNoticeComponent {}
```

```latte title="packages/layout-demo/resources/views/gallery-notice.latte"
<p class="layout-demo-gallery-notice{if isset($class)} {$class}{/if}">
    Default notice from base layout
</p>
```

Because `GalleryNoticeComponent` has no `data()` method, a `MergeProps` or `ReplaceProps` operation that adds a `class` key will pass that value straight to `$class` in the template.

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
        $id = (int) ($props['gallery'] ?? 1);

        $items = match ($id) {
            2 => [
                new Item(id: 1, label: 'Second Gallery Item A'),
                new Item(id: 2, label: 'Second Gallery Item B'),
            ],
            3 => [
                new Item(id: 1, label: 'Third Gallery Item A'),
                new Item(id: 2, label: 'Third Gallery Item B'),
                new Item(id: 3, label: 'Third Gallery Item C'),
            ],
            default => [
                new Item(id: 1, label: 'Alpha'),
                new Item(id: 2, label: 'Beta'),
                new Item(id: 3, label: 'Gamma'),
            ],
        };

        $title = match ($id) {
            2 => 'Gallery Two',
            3 => 'Gallery Three',
            default => 'Gallery One',
        };

        return new GalleryEntity(title: $title, items: $items);
    }
}
```

### Wiring with `Provide`

In the layout file, declare the provider inside the `context` array:

```php
new Provide(
    token: GalleryToken::class,              // key in the context bag
    provider: GalleryContextProvider::class, // ContextProvider FQCN
    props: ['gallery' => Source::query('gallery', 1, 'int')],
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
// Read the 'page' segment as an integer
Source::route('page', 'int');
```

Allowed casts: `'string'` (default), `'int'`, `'bool'`.

### `Source::query()`

Reads a query-string parameter with an optional default value.

```php
// Read ?gallery=, default to 1, cast to int
Source::query('gallery', 1, 'int');
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

## Handles

A **handle** is the key that connects a layout to a request. When the middleware receives an incoming request it resolves the controller action, looks up the compiled tree whose `handle` matches, and renders it. Think of handles as Magento layout handles: named identifiers that select which layout tree to render.

### What a handle is

Every `Layout` that is meant to respond to a real HTTP request sets `handle` to a `[ControllerClass::class, 'actionMethod']` pair or to an arbitrary string:

```php
// Controller-action pair — matches the request automatically via routing
handle: [LayoutDemoController::class, 'show'],

// Named string handle — useful for default, inherited, and dynamic handles
handle: 'layout_demo.variant.featured',
```

The compiled artifact maps every handle key to its resolved component tree. At runtime the middleware looks up the key and renders the corresponding tree — no compilation happens on the hot path.

### The default handle

The special string handle `'default'` acts as a sitewide base that is merged into every other compiled tree. Placements declared in the default handle appear in every page. Use it for global UI elements such as site-wide notices, banners, or analytics snippets.

```php title="packages/layout-demo/layout/default.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\SitewideNoticeComponent;

return new Layout(
    handle: 'default',
    extends: null,
    slots: [
        'content' => [
            new Place(
                component: SitewideNoticeComponent::class,
                name: 'default.sitewide_notice',
                props: [],
                slots: [],
                template: 'layout-demo::sitewide-notice',
            ),
        ],
    ],
);
```

Constraints on the default handle:

- Must not declare `extends` — it has no parent shell.
- Must not declare `inherits` — it is always a root node.
- Must not declare `handleProviders` — dynamic handle resolution is not supported at the default level.

### Inheriting a handle

A layout can declare `inherits:` to copy the full compiled tree of another handle and then apply its own `operations` on top. This is useful when one route is a strict variant of another — it has the same component tree but with a small tweak (for example, removing the footer for an embedded view).

```php title="packages/layout-demo/layout/layout_demo_child.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Operation\Remove;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;

return new Layout(
    handle: 'layout_demo_child',
    extends: null,
    inherits: LayoutDemoController::class . '::show',
    operations: [
        new Remove(name: 'layout_demo.gallery_footer'),
    ],
);
```

How inheritance works:

1. The compiler resolves the parent handle (`LayoutDemoController::show`) into its full component tree.
2. The child's `operations` are applied on top of that tree, exactly as extension-file operations would be.
3. The result is a new, independent compiled tree keyed to `'layout_demo_child'`. Changes to the parent do not propagate at runtime — both trees are compiled independently.

`inherits:` takes a string handle key. For controller-action pairs write it as `ClassName::methodName`.

`Remove` can be used in `operations` to strip placements that the parent tree contains but the child does not need, as shown above.

### Dynamic handles

A **dynamic handle** is a handle whose tree is merged into the base tree at runtime, based on request context. This pattern extends a layout with additional placements only for certain requests — for example, showing a "featured" callout only when a query parameter signals a featured variant.

Dynamic handles are provided by classes that implement `HandleProvider`:

```php
namespace Markommerce\Layout\Contracts;

interface HandleProvider
{
    /**
     * @param array<string, mixed> $props
     * @return list<string>
     */
    public function provide(array $props): array;
}
```

The provider receives resolved props (declared in `ProvideHandle::$props`) and returns zero or more handle keys. Every key returned must correspond to a handle that exists in the compiled artifact.

To tell the compiler which handle keys a provider can return, decorate the class with `#[ProvidesHandles]`:

```php title="packages/layout-demo/src/Handle/GalleryVariantHandleProvider.php"
<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Handle;

use Markommerce\Layout\Attributes\ProvidesHandles;
use Markommerce\Layout\Contracts\HandleProvider;

#[ProvidesHandles(handles: ['layout_demo.variant.featured'])]
class GalleryVariantHandleProvider implements HandleProvider
{
    /**
     * @param array<string, mixed> $props
     * @return list<string>
     */
    public function provide(array $props): array
    {
        if (($props['variant'] ?? '') === 'featured') {
            return ['layout_demo.variant.featured'];
        }

        return [];
    }
}
```

The `#[ProvidesHandles]` attribute lists every handle key the provider can return. The compiler reads this list at compile time and validates that each key exists in the artifact — a provider that returns a handle key not listed in the attribute will throw `UnknownDynamicHandleException` at runtime.

Wire a provider into a layout using `ProvideHandle` in the `handleProviders` array:

```php title="packages/layout-demo/layout/layout_demo.php (excerpt)"
use Markommerce\Layout\ProvideHandle;
use Markommerce\LayoutDemo\Handle\GalleryVariantHandleProvider;

return new Layout(
    handle: [LayoutDemoController::class, 'show'],
    // ... context, slots ...
    handleProviders: [
        new ProvideHandle(
            provider: GalleryVariantHandleProvider::class,
            props: ['variant' => Source::query('variant', '', 'string')],
        ),
    ],
);
```

`ProvideHandle::$props` works exactly like `Provide::$props` for context providers: it declares which `Source` descriptors are resolved and forwarded to `HandleProvider::provide()` as the `$props` array.

Important rules for dynamic handles:

- Providers run **after** `ContextProvider`s have executed, so `props` can read from the resolved context bag via `Source::context()`.
- Providers must return **statically known** handle keys — every possible return value must appear in `#[ProvidesHandles]`.
- A dynamic handle's own layout file must not declare `handleProviders`. Chaining providers is not supported and will throw `ChainedHandleProviderException`.
- Placement names in the dynamic handle's tree must not collide with names in the base tree. Collisions throw `DynamicHandleConflictException`.

### Resolution order

When the compiler builds a compiled tree for a handle, it applies the following steps in order:

1. **`extends`** — Merge the `LayoutDefinition` shell (e.g. `OneColumnLayout`) to provide the outer template and named slots.
2. **`inherits`** — Copy the full compiled tree of the parent handle and apply this layout's own `operations` on top.
3. **Default handle** — Merge the `'default'` handle's placements into every compiled tree.
4. **Own ops** — Apply the `operations` declared directly in this layout file.
5. **Extension-file ops** — Collect all `LayoutExtension` files targeting this handle and apply their operations in ascending `priority` order.
6. **Runtime dynamic-handle merge** — At request time, run each `HandleProvider`, look up the returned handle trees in the artifact, and merge their placements into the rendered tree.

## Extending a Layout

Any module can add, remove, or modify placements in an existing compiled layout without touching the original file. Extension files live at `{module}/layout/extensions/{name}.php` and return a `LayoutExtension`.

The layout-demo extension exercises all nine available operations:

```php title="packages/layout-demo/layout/extensions/layout_demo_extension.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\Append;
use Markommerce\Layout\Operation\InsertAfter;
use Markommerce\Layout\Operation\InsertBefore;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\Prepend;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Operation\Replace;
use Markommerce\Layout\Operation\ReplaceProps;
use Markommerce\Layout\Operation\WrapWith;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\FeaturedBadgeComponent;
use Markommerce\LayoutDemo\Component\GalleryAnnouncementComponent;
use Markommerce\LayoutDemo\Component\GalleryCustomComponent;
use Markommerce\LayoutDemo\Component\GallerySubtitleComponent;
use Markommerce\LayoutDemo\Component\GallerySummaryComponent;
use Markommerce\LayoutDemo\Component\GalleryWrapperDecorator;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;

return new LayoutExtension(
    handle: [LayoutDemoController::class, 'show'],
    operations: [
        // Insert a badge before each repeat-slot item
        new InsertBefore(
            anchorName: 'layout_demo.item',
            placement: new Place(
                component: FeaturedBadgeComponent::class,
                name: 'layout_demo.featured_badge',
                props: [],
                slots: [],
                template: 'layout-demo::featured-badge',
            ),
        ),
        // Insert a subtitle after the gallery header
        new InsertAfter(
            anchorName: 'layout_demo.gallery_header',
            placement: new Place(
                component: GallerySubtitleComponent::class,
                name: 'layout_demo.gallery_subtitle',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-subtitle',
            ),
        ),
        // Wrap the gallery section with a decorator
        new WrapWith(
            name: 'layout_demo.gallery',
            decorator: GalleryWrapperDecorator::class,
        ),
        // Add a CSS class to the footer via prop merge
        new MergeProps(
            name: 'layout_demo.gallery_footer',
            props: ['class' => 'highlighted'],
        ),
        // Replace all props on the notice (adds a class, removes any defaults)
        new ReplaceProps(
            name: 'layout_demo.notice',
            props: ['class' => 'extension-notice'],
        ),
        // Replace the placeholder with a custom component
        new Replace(
            name: 'layout_demo.placeholder',
            placement: new Place(
                component: GalleryCustomComponent::class,
                name: 'layout_demo.custom',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-custom',
            ),
        ),
        // Remove the deprecated element entirely
        new Remove(
            name: 'layout_demo.deprecated',
        ),
        // Prepend an announcement to the top of the content slot
        new Prepend(
            slotPath: 'content',
            placement: new Place(
                component: GalleryAnnouncementComponent::class,
                name: 'layout_demo.announcement',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-announcement',
            ),
        ),
        // Append a summary to the bottom of the content slot
        new Append(
            slotPath: 'content',
            placement: new Place(
                component: GallerySummaryComponent::class,
                name: 'layout_demo.summary',
                props: [],
                slots: [],
                template: 'layout-demo::gallery-summary',
            ),
        ),
    ],
    priority: 0,
);
```

### Operations reference

Every operation targets a placement by name or by slot path. Operations that target a named placement recurse through the entire tree — including into repeat-slot children — so an operation can reach any placement regardless of nesting depth.

#### `InsertBefore`

Inserts a new `Place` immediately before the named placement in the same sibling list. When the anchor is inside a repeat slot, the inserted placement appears before every rendered item.

```php
new InsertBefore(
    anchorName: 'layout_demo.item',     // target placement name
    placement: new Place(...),           // the new placement to insert
),
```

#### `InsertAfter`

Inserts a new `Place` immediately after the named placement.

```php
new InsertAfter(
    anchorName: 'layout_demo.gallery_header',
    placement: new Place(...),
),
```

#### `Prepend`

Prepends a new placement to the beginning of a top-level named slot. Use this to inject content at the very start of a slot without knowing what placements the base layout put there.

```php
new Prepend(
    slotPath: 'content',    // top-level slot name
    placement: new Place(...),
),
```

#### `Append`

Appends a new placement to the end of a top-level named slot.

```php
new Append(
    slotPath: 'content',
    placement: new Place(...),
),
```

#### `WrapWith`

Wraps the named placement with a `DecoratorInterface` implementation. The decorator renders its outer markup and calls `$inner()` to render the original placement inside it.

```php
new WrapWith(
    name: 'layout_demo.gallery',
    decorator: GalleryWrapperDecorator::class,
),
```

```php title="packages/layout-demo/src/Component/GalleryWrapperDecorator.php"
class GalleryWrapperDecorator implements DecoratorInterface
{
    public function render(callable $inner): string
    {
        return '<div class="gallery-wrapper">' . $inner() . '</div>';
    }
}
```

#### `MergeProps`

Merges additional prop bindings into the named placement. Existing props are preserved; only the keys listed here are added or overwritten.

```php
new MergeProps(
    name: 'layout_demo.gallery_footer',
    props: ['class' => 'highlighted'],
),
```

For prop-only components (no `data()` method), merged props are passed directly to the template as variables.

#### `ReplaceProps`

Replaces the entire prop map of the named placement. All props declared in the base layout are discarded and replaced with the new map.

```php
new ReplaceProps(
    name: 'layout_demo.notice',
    props: ['class' => 'extension-notice'],
),
```

Use `ReplaceProps` when you need to completely reset the props, for example to remove a default prop that would otherwise be merged. Prefer `MergeProps` when you only need to add or override specific keys.

#### `Replace`

Replaces the named placement with an entirely different `Place`. The original component is removed and the new one takes its position.

```php
new Replace(
    name: 'layout_demo.placeholder',
    placement: new Place(
        component: GalleryCustomComponent::class,
        name: 'layout_demo.custom',
        props: [],
        slots: [],
        template: 'layout-demo::gallery-custom',
    ),
),
```

#### `Remove`

Removes the named placement from the tree entirely.

```php
new Remove(
    name: 'layout_demo.deprecated',
),
```

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
