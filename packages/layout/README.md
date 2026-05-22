# markommerce/layout

Placement-agnostic layout system for Markommerce --- typed component trees, compile-validated slot structures, iteration slots, and a closed extension vocabulary.

Unlike `marko/layout`, components carry no `handle` or `slot` annotations; placement is declared at the layout level. Component data is a typed DTO extending `ExtensibleData`, trees are validated at compile time, and third-party code extends layouts through a fixed set of operations rather than arbitrary tree manipulation.

## Installation

```bash
composer require markommerce/layout
```

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Layout\Layout;
use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\InsertAfter;
use Markommerce\Layout\Place;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;

// 1. Typed DTO for a placement-agnostic component
readonly class ProductCardData extends ExtensibleData
{
    public function __construct(
        public string $sku,
        public string $name,
        public ExtensionBag $extensions = new ExtensionBag([]),
    ) {
        parent::__construct($extensions);
    }
}

// 2. Layout definition: a product list with a repeat slot over each product
$layout = new Layout(
    handle: 'catalog.category',
    extends: null,
    context: [],
    slots: [
        'main' => Slot::repeat(
            dataKey: 'products',
            yields: ProductCardData::class,
            as: 'product',
            children: [
                new Place(
                    component: 'product-card',
                    name: 'productCard',
                    props: ['sku' => Source::iterated('product', 'sku')],
                    slots: [],
                ),
            ],
        ),
    ],
);

// 3. Extension: insert a badge before the product card
$extension = new LayoutExtension(
    handle: 'catalog.category',
    operations: [
        new InsertAfter(
            anchorName: 'productCard',
            placement: new Place('sale-badge', 'saleBadge', [], []),
        ),
    ],
    priority: 10,
);
```

## Compile

Discover all layout definitions and write the compiled artifact:

```bash
vendor/bin/marko layout:compile
```

The artifact is written to `var/cache/markommerce/layouts.php` and is loaded at runtime instead of re-resolving the tree on every request.

## Documentation

Full usage, API reference, and examples: [markommerce/layout](https://markommerce.dev/docs/packages/layout/)
