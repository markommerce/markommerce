---
title: markommerce/catalog-scope
description: Scope storage bridge for catalog entities --- adds scoped override support to Product and Category via companion entities.
---

Scope storage bridge for catalog entities. `markommerce/catalog-scope` adds `HasScopesInterface` support to `markommerce/catalog`'s `Product` and `Category` entities without modifying those entities. It does this through two companion entity classes --- `ProductScopedOverrides` and `CategoryScopedOverrides` --- that extend the catalog tables via single-table inheritance and carry the `scopes` JSON column. The package also ships `ScopedProductGridComponent`, a `#[Preference]` override of the catalog `ProductGridComponent` that resolves locale-scoped names and descriptions via `ScopeResolver` before returning component data.

## Installation

```bash
composer require markommerce/catalog-scope
```

This package requires `markommerce/catalog` and `markommerce/scope`. To persist and query scoped overrides you must also install a scope driver:

```bash
composer require markommerce/scope-pgsql
```

## Usage

### Companion entities

`ProductScopedOverrides` and `CategoryScopedOverrides` implement `HasScopesInterface` via the `HasScopes` trait. They extend the catalog tables using Marko's single-table inheritance mechanism: the `#[Table(extends: Product::class)]` attribute merges a `scopes` JSON column into the parent `catalog_products` table. No separate migration is required --- the column appears automatically when `SchemaRegistry::registerEntities()` discovers both classes.

```php title="packages/catalog-scope/src/Entity/ProductScopedOverrides.php"
<?php

declare(strict_types=1);

namespace Markommerce\CatalogScope\Entity;

use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table(extends: Product::class)]
class ProductScopedOverrides extends Entity implements HasScopesInterface
{
    use HasScopes;
}
```

`CategoryScopedOverrides` follows the same pattern, extending `Category::class`.

### Setting and reading overrides

Once the companion is attached to a product, use `ScopeResolver` to set and read scoped values:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

$product = new Product();
$product->sku = 'SKU-001';
$product->name = 'Shirt';

$overrides = new ProductScopedOverrides();
$product->attachCompanion($overrides);

$scopeResolver->setOverride(
    $product,
    'name',
    'Hemd',
    ScopeSignature::fromArray(['locale' => 'de']),
);

$productRepository->save($product);

// Resolve with active locale context
$scopeContext->in('locale', 'de');
$name = $scopeResolver->resolved($product, 'name'); // 'Hemd'
```

### Hydration and the companion

When a product is loaded from the database with a full column SELECT (including the `scopes` column), Marko's `EntityHydrator` automatically attaches a hydrated `ProductScopedOverrides` instance as a companion:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;

/** @var Product $product */
$product = $productRepository->find($id);

/** @var ProductScopedOverrides|null $overrides */
$overrides = $product->companion(ProductScopedOverrides::class);
```

If the `scopes` column is absent from the result set (for example, a partial SELECT), the companion is `null` and `ScopeResolver` falls back to the raw column value.

### ScopedProductGridComponent

`ScopedProductGridComponent` is declared with `#[Preference(replaces: ProductGridComponent::class)]`. When this package is installed, Marko's container automatically resolves it wherever `ProductGridComponent` is requested. The override calls `ScopeResolver::resolved()` on each product's `name` and `description` fields before returning `ProductGridData`:

```php
<?php

declare(strict_types=1);

use Marko\Core\Attributes\Preference;
use Markommerce\Catalog\Component\ProductGridComponent;
use Markommerce\Scope\Resolver\ScopeResolver;

#[Preference(replaces: ProductGridComponent::class)]
class ScopedProductGridComponent extends ProductGridComponent
{
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CategoryAssignmentService $categoryAssignmentService,
        private ScopeResolver $scopeResolver,
    ) {
        parent::__construct($categoryRepository, $categoryAssignmentService);
    }
}
```

No manual wiring is required --- installing the package and running Marko's module system is sufficient to activate the override.

### Registering scoped fields

`catalog-scope` provides the storage layer (the `scopes` column). It does not declare which catalog fields are locale-scoped. That declaration is the responsibility of bridge packages like `markommerce/catalog-locale`, which register field→axis mappings via `ScopedFieldRegistry` at boot time.

See [markommerce/catalog-locale](/docs/packages/catalog-locale/) for the canonical bridge pattern.

### Seeder

The package ships a `catalog-locale` seeder that attaches `locale:de` and `locale:fr` overrides to every product and category created by the `catalog` seeder:

```bash
php artisan db:seed --seeder=catalog-locale
```

The seeder (`CatalogLocaleSeeder`) reads all existing products and categories and writes German and French name/description overrides via `attachCompanion()` + `setOverride()`. It is declared with `order: 10` so it runs after the base `catalog` seeder (which has no explicit order and therefore runs first).

## API Reference

### Entities

#### `ProductScopedOverrides`

Table extension: `catalog_products` (via `#[Table(extends: Product::class)]`)

Implements `HasScopesInterface` via the `HasScopes` trait. Carries a `scopes` JSON column that stores per-axis override values for `Product` properties.

| Method | Description |
|--------|-------------|
| `setOverride(string $signature, string $property, mixed $value): void` | Store a scoped value. The signature format is `axis:path` (e.g. `locale:de`). |
| `clearOverride(string $signature, string $property): void` | Remove a scoped override. |
| `override(string $signature, string $property): mixed` | Read a raw stored override (no fallback). |
| `overrides(): array` | Return all stored overrides as an associative array. |

#### `CategoryScopedOverrides`

Table extension: `catalog_categories` (via `#[Table(extends: Category::class)]`)

Same interface as `ProductScopedOverrides`, applied to `Category` properties.

### Component

#### `ScopedProductGridComponent`

`#[Preference(replaces: ProductGridComponent::class)]`

| Method | Description |
|--------|-------------|
| `data(Category $category): ProductGridData` | Returns product grid data with locale-resolved `resolvedNames` and `resolvedDescs` maps, replacing the raw values from the base component. |

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- Provides `Product` and `Category` entities extended by this package
- [markommerce/scope](/docs/packages/scope/) --- Provides `HasScopesInterface`, `ScopeResolver`, and the resolution infrastructure
- [markommerce/catalog-locale](/docs/packages/catalog-locale/) --- Bridge that registers catalog fields as locale-scoped via `ScopedFieldRegistry`
- [markommerce/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver required to persist and query scoped overrides
