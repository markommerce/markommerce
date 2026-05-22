---
title: markommerce/layout-demo
description: Reference module for the Markommerce layout system --- showcases context providers, typed DTOs, iteration slots, and extension operations end-to-end.
---

`markommerce/layout-demo` is the canonical reference implementation and smoke test for the Markommerce layout system. It is a development-only package (`require-dev`) that demonstrates the full integration path --- context providers, typed component DTOs, `Slot::repeat()` iteration, and all three extension operations (`InsertBefore`, `WrapWith`, `MergeProps`) --- working end-to-end in a real request. Use it as living documentation when building your own layout modules.

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

Set `layout_demo.enabled` to `true` in your local config override to activate the `GET /markommerce/_demo/layout/{id}` route:

```php title="config/local/layout_demo.php"
<?php

declare(strict_types=1);

return [
    'layout_demo.enabled' => true,
];
```

## Usage

Once `layout_demo.enabled` is `true`, visit `/markommerce/_demo/layout/1` (or `/markommerce/_demo/layout/2` for a second fixture gallery) in your browser. The page renders:

- A **gallery header** component wrapping the gallery title.
- A **gallery** component listing items, each rendered by an **item** component whose label is uppercased by `DefaultLabelFormatter`.
- A **gallery footer** component carrying an extra CSS class injected via `MergeProps`.

The extension file (`layout/extensions/layout_demo_extension.php`) applies three operations to the same layout tree to show how third-party modules mutate layouts:

- `InsertBefore` --- inserts a `FeaturedBadgeComponent` before the gallery.
- `WrapWith` --- wraps the gallery header in `GalleryWrapperDecorator`.
- `MergeProps` --- merges `['class' => 'highlighted']` into the gallery footer placement.

These behaviors together constitute the full smoke test for the layout system.

## Layout Definition

The base layout is defined in `layout/layout_demo.php`. It extends `OneColumnLayout`, registers a `GalleryContextProvider` that hydrates a `GalleryEntity` from the route `{id}` parameter, and places three components into the `content` slot:

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

Key patterns this layout demonstrates:

- **`Source::route()`** --- resolves the `{id}` route parameter and casts it to `int` before passing it as a prop to the context provider.
- **`Source::context()`** --- reads the resolved `GalleryEntity` from the context bag using `GalleryToken` as the key.
- **`Source::query()`** --- reads the `page` query parameter with a default of `1`.
- **`Source::parentData()`** --- reads `title` off the parent `GalleryData` DTO and passes it down to `ItemComponent`.
- **`Source::service()`** --- resolves `LabelFormatterInterface` from the container and injects it as a prop.
- **`Slot::repeat()`** --- iterates `GalleryData::$items`, wrapping each `Item` in `ItemIteration` so children can read it via `Source::iterated()`.

## Extension File

The extension file shows all three mutation operations together:

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

## Context Provider

`GalleryContextProvider` implements `ContextProvider` and hydrates a `GalleryEntity` from the route id. This is the canonical pattern for feeding domain data into a layout tree:

```php title="packages/layout-demo/src/Context/GalleryContextProvider.php"
<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\LayoutDemo\Context\GalleryToken;
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
        // ... load or build GalleryEntity from $id
        return new GalleryEntity(title: 'Gallery One', items: [/* ... */]);
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
    props: ['id' => Source::route('id', 'int')],
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
    LabelFormatterInterface::class => DefaultLabelFormatter::class,
];
```

Override it in your own module's `module.php` to substitute a custom formatter without touching the demo package.

## Living Documentation

Because `markommerce/layout-demo` is a real, runnable module rather than isolated unit tests, it validates the full layout integration path every time it renders. Treat it as the canonical reference when:

- Authoring a new context provider --- compare against `GalleryContextProvider`.
- Using `Slot::repeat()` for the first time --- the `GalleryComponent` / `ItemComponent` pair is the reference example.
- Wiring `Source::service()` to inject a service into a component prop --- `ItemComponent`'s `labelFormatter` prop shows this pattern.
- Writing a `LayoutExtension` file --- the extension file exercises all three commonly used operations side by side.

## API Reference

### `LabelFormatterInterface`

| Method | Return type | Description |
|---|---|---|
| `format(string $label)` | `string` | Transform a label string. Default implementation uppercases it. |

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

### Iteration Token

| Class | Description |
|---|---|
| `ItemIteration` | Marked with `#[IteratesOver(Item::class)]`; used as the `as` argument in `Slot::repeat()` and as the token for `Source::iterated()` |

## Related Packages

- [markommerce/layout](/docs/packages/layout/) --- the layout kernel that supplies `Layout`, `Place`, `Slot`, `Provide`, `Source`, `LayoutExtension`, all operation classes, and the `DecoratorInterface`.
- [markommerce/theme-blank](/docs/packages/theme-blank/) --- provides `OneColumnLayout`, which this demo extends.
- [Working with Layouts](/docs/guides/working-with-layouts/) --- step-by-step guide for defining layouts, wiring providers, using repeat slots, and extending layouts.
