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

## Exceptions

All exceptions extend `LayoutException` (which extends `MarkoException`) and carry `message`, `context`, and `suggestion` fields.

| Exception | Factory | Thrown when |
|---|---|---|
| `UnknownContextException` | `forContext(string $token, string $layout)` | A `Source::context()` reference names a token not declared in the layout's `context` array |
| `UnknownIterationException` | `forIteration(string $token, string $placement)` | A `Source::iterated()` reference names an iteration variable not in scope |
| `TypeMismatchException` | `forProp(string $prop, string $expected, string $actual)` | A prop value type does not match the declared type |
| `RepeatTypeMismatchException` | `forItem(string $yields, string $actual)` | An iteration slot item does not match the `yields` type |
| `DanglingAnchorException` | `forAnchor(string $anchor, string $extensionFile)` | An extension operation references an anchor that does not exist in the target layout |
| `DuplicateNameException` | `forName(string $name)` | Two placements share the same name within a resolved tree |
| `MissingDataKeyException` | `forKey(string $key, string $component)` | Runtime data is missing a key required by a component |
| `ExtensionConflictException` | `forConflict(string $opA, string $opB, int $priority)` | Two extensions at the same priority conflict on the same anchor |
| `MissingPropException` | `forProp(string $prop, string $component)` | A required prop is absent from a placement |
| `InvalidSourceTypeException` | `forSource(string $source, string $value, string $targetType)` | A source value cannot be coerced to the declared target type |
| `CircularInheritanceException` | `forChain(list<string> $chain)` | A cycle is detected in the `inherits:` chain of handles |
| `UnknownParentHandleException` | `forParent(string $parent, string $child)` | An `inherits:` declaration references a handle that does not exist |
| `DefaultHandleConflictException` | `forField(string $field)` | The reserved `'default'` handle declares `extends`, `inherits`, or `handleProviders` |
| `DynamicHandleConflictException` | `forCollidingPlacement(string $placementName, string $baseHandle, string $dynamicHandle)` | Merging a dynamic handle tree introduces a placement name that already exists in the base handle |
| `UnknownDynamicHandleException` | `forHandle(string $handle, string $providerClass)` | A `HandleProvider` returns a handle key that is not present in the compiled artifact |
| `DuplicateContextTokenException` | `forToken(string $token, string $sourceHandle, string $targetHandle)` | An inheritance or default merge introduces a context token already defined on the target handle |
| `ChainedHandleProviderException` | `forChain(string $providerClass, string $dynamicHandle)` | A dynamically resolved handle's tree itself declares `handleProviders` |

## Documentation

Full usage, API reference, and examples: [markommerce/layout](https://markommerce.dev/docs/packages/layout/)
