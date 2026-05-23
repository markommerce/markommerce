# markommerce/layout-demo

Reference module for the Markommerce layout system — showcases context providers, typed DTOs, iteration slots, and extension operations (InsertBefore, WrapWith, MergeProps) end-to-end.

## Installation

This package is a development-only dependency. Install it via `require-dev`:

```bash
composer require --dev markommerce/layout-demo
```

## Quick Example

```php
// resources/views/layout/layout_demo.php
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
                        children: [/* ... */],
                    ),
                ],
                template: 'layout-demo::gallery',
            ),
        ],
    ],
);
```

## Demo Route

The demo is available at `/markommerce/_demo/layout/{id}` when `layout_demo.enabled` is `true`.

## Documentation

Full usage, API reference, and examples: [markommerce/layout-demo](https://markommerce.dev/docs/packages/layout-demo/)
