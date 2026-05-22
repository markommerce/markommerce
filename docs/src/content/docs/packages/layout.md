---
title: markommerce/layout
description: Layout module for Markommerce — page layout resolution and rendering.
---

Layout module for Markommerce. `markommerce/layout` provides a placement-agnostic component-tree system: components declare no slots or handles — placement is described entirely in layout definition files, trees are compile-validated, and third-party modules extend layouts through a closed vocabulary of typed operations.

Unlike `marko/layout`, component data is a typed DTO rather than a freeform array, iteration slots are first-class, and the extension model gives consumers safe, auditable mutations rather than arbitrary tree overrides.

## Guides

- [Working with Layouts](/docs/guides/working-with-layouts/) — Step-by-step how-to guide for defining layouts, wiring providers, using repeat slots, and extending layouts.

## Installation

```bash
composer require markommerce/layout
```

The package declares itself as a `marko-module` and registers `MarkommerceLayoutMiddleware` as global middleware (priority 30) via `module.php`.

## Usage

### Defining a layout

A layout definition file returns a `Layout` value object from `{module}/layout/{name}.php`. The file's `handle` property ties it to a controller action:

```php title="packages/catalog/layout/category_show.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Component\ProductCard;
use Markommerce\Catalog\Component\ProductGridComponent;
use Markommerce\Catalog\Context\CategoryDataProvider;
use Markommerce\Catalog\Context\CategoryToken;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Iteration\ProductIteration;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

return new Layout(
    handle: [CategoryController::class, 'show'],
    extends: OneColumnLayout::class,
    context: [
        new Provide(
            token: CategoryToken::class,
            provider: CategoryDataProvider::class,
            props: ['id' => Source::route('id', 'int')],
        ),
    ],
    slots: [
        'content' => [
            new Place(
                component: ProductGridComponent::class,
                name: 'catalog.product_grid',
                props: ['category' => Source::context(CategoryToken::class)],
                slots: [
                    'products' => Slot::repeat(
                        dataKey: 'products',
                        yields: Product::class,
                        as: ProductIteration::class,
                        children: [
                            new Place(
                                component: ProductCard::class,
                                name: 'catalog.product_card',
                                props: ['product' => Source::iterated(ProductIteration::class)],
                                slots: [],
                            ),
                        ],
                    ),
                ],
            ),
        ],
    ],
);
```

The `extends` property names a `LayoutDefinition` class whose slots become the outer shell. The layout file fills those slots with `Place` objects and `Slot` repeat groups.

### Typed component data DTOs

Components return a typed DTO that extends `ExtensibleData`. Third-party modules can attach extra data via `ExtensionAttribute` implementations without subclassing the DTO:

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;

readonly class ProductCardData extends ExtensibleData
{
    public function __construct(
        public string $sku,
        public string $name,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}
```

A component class exposes a `data()` method that returns its DTO. Props declared in the layout file are resolved by the runtime and passed as arguments:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Data\ProductCardData;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Layout\ExtensionBag;

class ProductCard
{
    public function data(Product $product): ProductCardData
    {
        return new ProductCardData(
            product: $product,
            resolvedName: $product->name ?? '',
            resolvedDesc: $product->description ?? '',
            inStock: true,
            extensions: new ExtensionBag(),
        );
    }
}
```

### Implementing a layout definition

Themes and base packages expose reusable outer shells by implementing `LayoutDefinition`. The `define()` method returns a `Layout` with `handle: null` (no routing handle — it exists only to be extended):

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Layout;

class OneColumnLayout implements LayoutDefinition
{
    public static function define(): Layout
    {
        return new Layout(
            handle: null,
            extends: null,
            context: [],
            slots: ['content' => []],
            template: 'theme-blank::layout/1column',
        );
    }
}
```

### Context providers

Context objects are made available to all components in a layout subtree via `Provide` + a `ContextProvider` implementation.

**Interface signature:**

```php
namespace Markommerce\Layout\Contracts;

interface ContextProvider
{
    public function provide(array $props): object;
}
```

The `$props` array contains the keys declared in `Provide::$props`, resolved at render time by the `Source` descriptors specified there. The returned object is stored in the context bag under the key `Provide::$token` and is available to any component in the subtree via `Source::context()`.

**Concrete example:**

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\ContextProvider;

class CategoryDataProvider implements ContextProvider
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {}

    /**
     * @param array<string, mixed> $props
     */
    public function provide(array $props): object
    {
        return $this->categoryRepository->find((int) $props['id']);
    }
}
```

Wire the provider in a layout file with a `Provide` declaration inside the `context` array:

```php
new Provide(
    token: CategoryToken::class,          // key in the context bag
    provider: CategoryDataProvider::class, // ContextProvider FQCN
    props: ['id' => Source::route('id', 'int')],
),
```

Any component prop in the same subtree can then read the resolved entity with `Source::context(CategoryToken::class)`.

### Sources

`Source` is a static factory for prop-resolution descriptors. Each source type tells the runtime where to read a prop's value at render time:

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\Source\Source;

// Read a route parameter and cast to int
Source::route('id', 'int');

// Read a query-string parameter with a default value
Source::query('page', 1, 'int');

// Read an object from the context bag by token class
Source::context(CategoryToken::class);

// Read a property on the current iterated item
Source::iterated(ProductIteration::class, 'sku');

// Read a key from the parent component's DTO
Source::parentData('inStock', 'bool');

// Resolve a service from the container
Source::service(PricingServiceInterface::class);
```

Allowed cast values for `route()`, `query()`, and `parentData()` are `'int'`, `'string'`, and `'bool'`.

### Repeat slots

`Slot::repeat()` iterates a collection from a component's DTO and renders child placements once per item:

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\Place;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;

Slot::repeat(
    dataKey: 'products',          // key on the parent DTO
    yields: Product::class,       // item type (for compile-time validation)
    as: ProductIteration::class,  // iteration context token
    children: [
        new Place(
            component: ProductCard::class,
            name: 'catalog.product_card',
            props: ['product' => Source::iterated(ProductIteration::class)],
            slots: [],
        ),
    ],
);
```

### Extension attributes

Third-party modules attach domain-specific data to any DTO extending `ExtensibleData` without modifying the original class:

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\ExtensionAttribute;

readonly class PromoBadgeExtension implements ExtensionAttribute
{
    public function __construct(
        public string $label,
        public string $color,
    ) {}
}

// Attach in a plugin on the component's data() method:
$data = $data->withExtension(new PromoBadgeExtension('Sale', 'red'));

// Read in the template:
$badge = $data->extensions->get(PromoBadgeExtension::class);
```

### Extending a layout

A `LayoutExtension` adds, removes, or modifies placements in an existing layout tree. Extension files live in `{module}/layout/extensions/{name}.php`:

```php
<?php

declare(strict_types=1);

use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\InsertAfter;
use Markommerce\Layout\Place;

return new LayoutExtension(
    handle: 'catalog.category_show',
    operations: [
        new InsertAfter(
            anchorName: 'catalog.product_card',
            placement: new Place('sale-badge', 'promo.sale_badge', [], []),
        ),
    ],
    priority: 10,
);
```

Operations are applied in ascending `priority` order. Lower numbers run first.

### Compiling layouts

Discover all layout definitions, resolve the extension tree, validate slot structure, and write the compiled artifact:

```bash
vendor/bin/marko layout:compile
```

The artifact is written to `var/cache/markommerce/layouts.php`. The `MarkommerceLayoutMiddleware` reads it on every request; no resolution happens at runtime.

In `dev` and `local` environments, `CompileIfStaleMiddleware` automatically recompiles whenever a layout source file is newer than the artifact. In production, register `layout:compile` as a deploy step.

## API Reference

### `Layout`

Value object representing a single compiled layout tree.

| Property | Type | Description |
|---|---|---|
| `$handle` | `array<int,string>\|string\|null` | Controller FQCN + action, or `null` for base layouts |
| `$extends` | `class-string\|null` | `LayoutDefinition` class to inherit slots and template from |
| `$inherits` | `string\|null` | Handle key of the parent layout whose compiled tree this layout copies before applying its own operations |
| `$context` | `list<Provide>` | Context provider declarations |
| `$slots` | `array<string, list<Place>\|Slot>` | Named slot contents |
| `$operations` | `list<Operation>` | Operations applied on top of the inherited tree (used with `inherits`) |
| `$handleProviders` | `list<ProvideHandle>` | Dynamic handle provider declarations |
| `$template` | `?string` | Template path (e.g. `theme-blank::layout/1column`) |

### `Place`

Describes a single component placement within a slot.

| Property | Type | Description |
|---|---|---|
| `$component` | `string` | FQCN of the component class |
| `$name` | `string\|null` | Unique name for this placement (used by extension operations) |
| `$props` | `array<string, mixed>` | Prop-to-source bindings |
| `$slots` | `array<string, list<Place>\|Slot>` | Nested slot contents |

### `Slot`

Represents an iteration slot. Created via `Slot::repeat()`:

| Parameter | Type | Description |
|---|---|---|
| `$dataKey` | `string` | Key on the parent DTO that holds the iterable |
| `$yields` | `string` | Item type class name |
| `$as` | `string` | Iteration context token class name |
| `$children` | `list<Place>` | Placements rendered per item |

### `Provide`

Registers a context provider for the layout subtree.

| Property | Type | Description |
|---|---|---|
| `$token` | `string` | Context token class name |
| `$provider` | `string` | `ContextProvider` implementation FQCN |
| `$props` | `array` | Props forwarded to `provide()` |

### `ProvideHandle`

Registers a dynamic handle provider for a layout. Declared in `Layout::$handleProviders`.

| Property | Type | Description |
|---|---|---|
| `$provider` | `class-string` | `HandleProvider` implementation FQCN |
| `$props` | `array<string, mixed>` | Props resolved and forwarded to `HandleProvider::provide()` |

### `LayoutExtension`

Carries a set of operations that mutate an existing compiled layout.

| Property | Type | Description |
|---|---|---|
| `$handle` | `array<int,string>\|string` | Handle of the layout to extend |
| `$operations` | `list<Operation>` | Ordered list of mutations |
| `$priority` | `int` | Application order (lower = earlier, default 0) |

### `ExtensibleData`

Abstract base class for component data DTOs.

| Method | Return type | Description |
|---|---|---|
| `withExtension(ExtensionAttribute $extension)` | `static` | Return a new DTO with the extension added to its bag |

### `ExtensionBag`

Immutable, typed collection of extension attributes keyed by class name.

| Method | Return type | Description |
|---|---|---|
| `get(class-string<T> $class)` | `T\|null` | Retrieve an extension by its class name |
| `with(ExtensionAttribute $extension)` | `self` | Return a new bag with the extension added |

### Contracts

| Interface | Description |
|---|---|
| `LayoutDefinition` | Implemented by layout shell classes; exposes `static define(): Layout` |
| `ContextProvider` | Implemented by context provider services; exposes `provide(array $props): object` |
| `HandleProvider` | Implemented by dynamic handle providers; exposes `provide(array $props): array` — returns `list<string>` of handle keys to merge at runtime |
| `DecoratorInterface` | Wraps rendered HTML; exposes `template(): string` and `wrap(string $innerHtml, array $data): string` |
| `ExtensionAttribute` | Marker interface for typed extension attributes |
| `Operation` | Marker interface for layout extension operations |

### Operations

| Class | Constructor | Description |
|---|---|---|
| `InsertBefore` | `string $anchorName, Place $placement` | Insert a placement before the named component |
| `InsertAfter` | `string $anchorName, Place $placement` | Insert a placement after the named component |
| `Prepend` | `string $slotPath, Place $placement` | Prepend a placement to a slot |
| `Append` | `string $slotPath, Place $placement` | Append a placement to a slot |
| `Remove` | `string $name` | Remove the named placement from the tree |
| `Replace` | `string $name, Place $placement` | Replace the named placement with another |
| `MergeProps` | `string $name, array $props` | Merge additional props into a named placement |
| `ReplaceProps` | `string $name, array $props` | Fully replace the props of a named placement |
| `WrapWith` | `string $name, class-string $decorator` | Wrap a named placement with a `DecoratorInterface` implementation |

### Source factory

All methods are on `Markommerce\Layout\Source\Source`:

| Method | Description |
|---|---|
| `Source::route(string $name, string $as = 'string')` | Read a route parameter |
| `Source::query(string $name, mixed $default = null, string $as = 'string')` | Read a query-string parameter |
| `Source::context(string $token, ?string $path = null)` | Read from the context bag by token |
| `Source::iterated(string $token, ?string $path = null)` | Read the current iterated item |
| `Source::parentData(string $key, string $as = 'string')` | Read a key from the parent component's DTO |
| `Source::service(string $class)` | Resolve a service from the container |

### CLI command

| Command | Description |
|---|---|
| `layout:compile` | Compile all discovered layouts into `var/cache/markommerce/layouts.php` |

### Middleware

| Class | Registered as | Description |
|---|---|---|
| `MarkommerceLayoutMiddleware` | Global, priority 30 | Matches request to compiled tree and renders HTML |
| `CompileIfStaleMiddleware` | Optional, add manually | Recompiles the artifact when source files are newer (dev/local only) |

### Attributes

| Attribute | Target | Description |
|---|---|---|
| `IteratesOver` | Class | Marks a component DTO class to declare which item type its slot iterates; used for compile-time slot validation |
| `ProvidesHandles` | Class | Declares which handle keys a `HandleProvider` implementation can return; required for compile-time validation of dynamic handle keys |

### Exceptions

All exceptions extend `LayoutException` and carry a `message`, `context`, and `suggestion` for actionable error output.

| Class | Factory | Thrown when |
|---|---|---|
| `DanglingAnchorException` | `forAnchor(string $name, string $handle)` | An extension operation targets a placement name that does not exist in the compiled tree |
| `DuplicateExtensionException` | `forExtension(string $handle, int $priority)` | Two extension files for the same handle share the same priority |
| `DuplicateNameException` | `forName(string $name, string $handle)` | Two placements in the same layout declare the same name |
| `ExtensionConflictException` | `forConflict(string $name, string $handle)` | An extension operation conflicts with another operation in the same extension |
| `InvalidLayoutFileException` | `forWrongType(string $filePath, string $actualType)` | A layout file does not return a `Layout` or `LayoutExtension` value object |
| `InvalidSourceTypeException` | `forSource(string $source, string $value, string $targetType)` | A source value cannot be coerced to the declared target type |
| `MissingDataKeyException` | `forKey(string $key, string $component)` | A `Slot::repeat()` `dataKey` does not exist on the component's DTO |
| `MissingPropException` | `forProp(string $prop, string $component)` | A required prop declared in the layout file is missing at render time |
| `MissingSlotInnerException` | `forSlot(string $slot, string $component)` | A `WrapWith` decorator does not call `$inner()` |
| `RepeatTypeMismatchException` | `forTypes(string $expected, string $actual)` | A `Slot::repeat()` `yields` type does not match the DTO property type |
| `TypeMismatchException` | `forTypes(string $expected, string $actual, string $prop)` | A prop value type does not match the component's `data()` parameter type |
| `UnknownContextException` | `forToken(string $token)` | A `Source::context()` references a token not declared in the layout's `context` array |
| `UnknownIterationException` | `forToken(string $token)` | A `Source::iterated()` references an iteration token outside a `Slot::repeat()` |
| `CircularInheritanceException` | `forChain(list<string> $chain)` | A cycle is detected in the `inherits:` chain |
| `UnknownParentHandleException` | `forParent(string $parent, string $child)` | An `inherits:` value references a handle that does not exist |
| `DefaultHandleConflictException` | `forField(string $field)` | The `'default'` handle declares `extends`, `inherits`, or `handleProviders` |
| `DynamicHandleConflictException` | `forCollidingPlacement(string $placementName, string $baseHandle, string $dynamicHandle)` | A dynamic handle tree declares a placement name already present in the base tree |
| `UnknownDynamicHandleException` | `forHandle(string $handle, string $providerClass)` | A `HandleProvider` returns a handle key that does not exist in the compiled artifact |
| `DuplicateContextTokenException` | `forToken(string $token, string $sourceHandle, string $targetHandle)` | An inheritance or default merge introduces a context token already defined on the target |
| `ChainedHandleProviderException` | `forChain(string $providerClass, string $dynamicHandle)` | A dynamic handle's resolved tree itself declares `handleProviders` |

## Related Packages

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- Provides `LayoutDefinition` implementations (`OneColumnLayout`, `TwoColumnsLeftLayout`, etc.)
- [markommerce/catalog](/docs/packages/catalog/) --- Uses `markommerce/layout` for the category storefront page
