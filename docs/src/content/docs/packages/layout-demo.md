---
title: markommerce/layout-demo
description: Reference module for the Markommerce layout system --- showcases context providers, typed DTOs, iteration slots, and extension operations end-to-end.
---

`markommerce/layout-demo` is the canonical reference implementation and smoke test for the Markommerce layout system. It is a development-only package (`require-dev`) that demonstrates the full integration path --- context providers, typed component DTOs, `Slot::repeat()` iteration, all nine extension operations, the default handle, handle inheritance, and dynamic handles --- working end-to-end in a real request. Use it as living documentation when building your own layout modules.

## Installation

Install the Composer package as a development dependency:

```bash
composer require --dev markommerce/layout-demo
```

## Configuration

Enable the demo route in your application config. The package ships a `config/layout_demo.php` file that defaults the route to disabled:

```php title="config/layout_demo.php"
<?php

declare(strict_types=1);

return [
    'enabled' => false,
];
```

Set `layout_demo.enabled` to `true` in your local config override to activate the `GET /markommerce/_demo/layout/{page}` route:

```php title="config/local/layout_demo.php"
<?php

declare(strict_types=1);

return [
    'layout_demo.enabled' => true,
];
```

## Usage

Once `layout_demo.enabled` is `true`, visit `/markommerce/_demo/layout/1` in your browser (append `?gallery=2` or `?gallery=3` for different fixture galleries; append `?variant=featured` to trigger the dynamic handle overlay). The page renders a gallery with several components inserted, removed, or modified by the extension file.

The package exercises these layout system features end to end:

- **Context providers** --- `GalleryContextProvider` hydrates a `GalleryEntity` from the `?gallery` query parameter.
- **Typed DTOs and `Slot::repeat()`** --- `GalleryComponent` returns `GalleryData` with a `list<Item>` that feeds the `items` repeat slot.
- **All nine extension operations** --- the extension file exercises `InsertBefore`, `InsertAfter`, `WrapWith`, `MergeProps`, `ReplaceProps`, `Replace`, `Remove`, `Prepend`, and `Append` in one file.
- **Default handle** --- `default.php` injects `SitewideNoticeComponent` into every compiled layout tree.
- **Handle inheritance** --- `layout_demo_child.php` copies the main layout tree and strips the footer via `Remove`.
- **Dynamic handles** --- `GalleryVariantHandleProvider` conditionally merges the `layout_demo.variant.featured` tree when `?variant=featured` is present.

## Layout Definition

The base layout is defined in `layout/layout_demo.php`. It extends `OneColumnLayout`, registers a `GalleryContextProvider` that hydrates a `GalleryEntity` from the `?gallery` query parameter, places six components into the `content` slot, and wires `GalleryVariantHandleProvider` as a dynamic handle provider:

```php title="packages/layout-demo/layout/layout_demo.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;
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
use Markommerce\LayoutDemo\Handle\GalleryVariantHandleProvider;
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
    handleProviders: [
        new ProvideHandle(
            provider: GalleryVariantHandleProvider::class,
            props: ['variant' => Source::query('variant', '', 'string')],
        ),
    ],
);
```

Key patterns this layout demonstrates:

- **`Source::query()`** --- reads the `?gallery` query parameter (default `1`) and passes it to the context provider.
- **`Source::context()`** --- reads the resolved `GalleryEntity` from the context bag using `GalleryToken` as the key.
- **`Source::route()`** --- reads the `{page}` route segment as an integer prop on `GalleryComponent`.
- **`Source::parentData()`** --- reads `title` off the parent `GalleryData` DTO and passes it down to `ItemComponent`.
- **`Source::service()`** --- resolves `LabelFormatterInterface` from the container and injects it as a prop.
- **`Slot::repeat()`** --- iterates `GalleryData::$items`, wrapping each `Item` in `ItemIteration` so children can read it via `Source::iterated()`.
- **`handleProviders`** --- wires `GalleryVariantHandleProvider` to conditionally merge the `layout_demo.variant.featured` dynamic handle tree.

## Extension File

The extension file exercises all nine available mutation operations in a single file:

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
        // Replace all props on the notice (adds a class)
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

## Context Provider

`GalleryContextProvider` implements `ContextProvider` and hydrates a `GalleryEntity` from the `?gallery` query parameter. This is the canonical pattern for feeding domain data into a layout tree:

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
            2 => [new Item(id: 1, label: 'Second Gallery Item A'), /* ... */],
            default => [new Item(id: 1, label: 'Alpha'), /* ... */],
        };

        $title = match ($id) {
            2 => 'Gallery Two',
            default => 'Gallery One',
        };

        return new GalleryEntity(title: $title, items: $items);
    }
}
```

`GalleryToken` is a plain empty class used as the context bag key:

```php
use Markommerce\LayoutDemo\Context\GalleryToken;

// In the layout file:
new Provide(
    token: GalleryToken::class,
    provider: GalleryContextProvider::class,
    props: ['gallery' => Source::query('gallery', 1, 'int')],
),

// In a component prop:
'gallery' => Source::context(GalleryToken::class),
```

## Decorator

`GalleryWrapperDecorator` implements `DecoratorInterface` and shows how `WrapWith` integrates:

```php title="packages/layout-demo/src/Component/GalleryWrapperDecorator.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\DecoratorInterface;

class GalleryWrapperDecorator implements DecoratorInterface
{
    public function template(): string
    {
        return '<div class="gallery-wrapper">{slot inner}</div>';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function wrap(string $innerHtml, array $data = []): string
    {
        return str_replace('{slot inner}', $innerHtml, $this->template());
    }
}
```

## Default Handle

`layout/default.php` demonstrates the reserved `'default'` handle. Placements declared here are prepended to every other compiled layout tree sitewide --- useful for banners, notices, or analytics snippets that must appear on every page.

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

The `'default'` handle must not declare `extends`, `inherits`, or `handleProviders`.

## Handle Inheritance

`layout/layout_demo_child.php` demonstrates `inherits:`. The child layout copies the full compiled tree of `LayoutDemoController::show` and then removes the footer placement:

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

`inherits:` takes a string handle key. For controller-action handles write it as `ClassName::methodName`. The `operations` array is applied on top of the copied tree at compile time, not at runtime.

## Dynamic Handle

`layout/layout_demo_variant_featured.php` is the tree that `GalleryVariantHandleProvider` merges at runtime when `?variant=featured` is present. It adds a callout component and removes the sitewide notice that the default handle injected:

```php title="packages/layout-demo/layout/layout_demo_variant_featured.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Place;
use Markommerce\LayoutDemo\Component\FeaturedCalloutComponent;

return new Layout(
    handle: 'layout_demo.variant.featured',
    extends: null,
    slots: [
        'content' => [
            new Place(
                component: FeaturedCalloutComponent::class,
                name: 'layout_demo.variant.featured_callout',
                props: [],
                slots: [],
                template: 'layout-demo::featured-callout',
            ),
        ],
    ],
    operations: [
        new Remove(name: 'default.sitewide_notice'),
    ],
);
```

`GalleryVariantHandleProvider` is annotated with `#[ProvidesHandles]` so the compiler can validate at compile time that the declared handle key exists in the artifact:

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

## Swappable Label Formatter

`LabelFormatterInterface` is the one extension point the demo exposes. The default implementation (`DefaultLabelFormatter`) uppercases every label:

```php title="packages/layout-demo/src/Service/LabelFormatterInterface.php"
<?php

declare(strict_types=1);

use Markommerce\LayoutDemo\Service\LabelFormatterInterface;

interface LabelFormatterInterface
{
    public function format(string $label): string;
}
```

The binding is declared in `module.php`:

```php title="packages/layout-demo/module.php"
<?php

declare(strict_types=1);

use Markommerce\LayoutDemo\Service\DefaultLabelFormatter;
use Markommerce\LayoutDemo\Service\LabelFormatterInterface;

return [
    'bindings' => [
        LabelFormatterInterface::class => DefaultLabelFormatter::class,
    ],
];
```

Override it in your own module's `module.php` to substitute a custom formatter without touching the demo package.

## Living Documentation

Because `markommerce/layout-demo` is a real, runnable module rather than isolated unit tests, it validates the full layout integration path every time it renders. Treat it as the canonical reference when:

- Authoring a new context provider --- compare against `GalleryContextProvider`.
- Using `Slot::repeat()` for the first time --- the `GalleryComponent` / `ItemComponent` pair is the reference example.
- Wiring `Source::service()` to inject a service into a component prop --- `ItemComponent`'s `labelFormatter` prop shows this pattern.
- Writing a `LayoutExtension` file --- the extension file exercises all nine operations side by side.
- Declaring sitewide placements via the `'default'` handle --- see `default.php`.
- Creating a layout variant via `inherits:` --- see `layout_demo_child.php`.
- Conditionally merging a dynamic handle tree at runtime --- see `GalleryVariantHandleProvider` and `layout_demo_variant_featured.php`.

## API Reference

### `LabelFormatterInterface`

| Method | Return type | Description |
|---|---|---|
| `format(string $label)` | `string` | Transform a label string. Default implementation uppercases it. |

### Components

| Class | Has `data()` | Description |
|---|---|---|
| `GalleryHeaderComponent` | Yes | Renders the gallery title |
| `GalleryComponent` | Yes | Renders the gallery list; provides the `items` repeat slot |
| `GalleryFooterComponent` | No | Prop-only footer; demonstrates `MergeProps` |
| `GalleryNoticeComponent` | No | Prop-only notice; demonstrates `ReplaceProps` |
| `GalleryPlaceholderComponent` | No | Placeholder; demonstrates `Replace` |
| `GalleryDeprecatedComponent` | No | Deprecated element; demonstrates `Remove` |
| `ItemComponent` | Yes | Renders a single gallery item inside the `items` repeat slot |
| `FeaturedBadgeComponent` | No | Inserted before each item by the extension file; demonstrates `InsertBefore` |
| `GallerySubtitleComponent` | No | Inserted after the header by the extension file; demonstrates `InsertAfter` |
| `GalleryAnnouncementComponent` | No | Prepended to the `content` slot; demonstrates `Prepend` |
| `GallerySummaryComponent` | No | Appended to the `content` slot; demonstrates `Append` |
| `GalleryCustomComponent` | No | Replaces the placeholder; used by the `Replace` operation |
| `FeaturedCalloutComponent` | No | Added by the `layout_demo.variant.featured` dynamic handle tree |
| `SitewideNoticeComponent` | No | Injected sitewide via the `'default'` handle |
| `GalleryWrapperDecorator` | N/A | `DecoratorInterface` implementation; wraps the gallery via `WrapWith` |

### Data Transfer Objects

| Class | Properties | Description |
|---|---|---|
| `GalleryData` | `string $title`, `int $page`, `list<Item> $items` | Returned by `GalleryComponent::data()` |
| `GalleryHeaderData` | `string $title` | Returned by `GalleryHeaderComponent::data()` |
| `ItemData` | `int $id`, `string $label`, `string $formattedLabel`, `string $galleryTitle` | Returned by `ItemComponent::data()` |

### Entities

| Class | Properties | Description |
|---|---|---|
| `GalleryEntity` | `string $title`, `list<Item> $items` | Hydrated by `GalleryContextProvider`; stored in the context bag under `GalleryToken` |
| `Item` | `int $id`, `string $label` | A single gallery item; iterated via `ItemIteration` |

### Handle Providers

| Class | `#[ProvidesHandles]` | Description |
|---|---|---|
| `GalleryVariantHandleProvider` | `['layout_demo.variant.featured']` | Returns `'layout_demo.variant.featured'` when the `?variant=featured` query parameter is set |

### Iteration Token

| Class | Description |
|---|---|
| `ItemIteration` | Marked with `#[IteratesOver(Item::class)]`; used as the `as` argument in `Slot::repeat()` and as the token for `Source::iterated()` |

## Related Packages

- [markommerce/layout](/docs/packages/layout/) --- the layout kernel that supplies `Layout`, `Place`, `Slot`, `Provide`, `Source`, `LayoutExtension`, all operation classes, and the `DecoratorInterface`.
- [markommerce/theme-blank](/docs/packages/theme-blank/) --- provides `OneColumnLayout`, which this demo extends.
- [Working with Layouts](/docs/guides/working-with-layouts/) --- step-by-step guide for defining layouts, wiring providers, using repeat slots, and extending layouts.
