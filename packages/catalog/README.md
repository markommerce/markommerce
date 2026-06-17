# markommerce/catalog

Products and categories for Markommerce --- globally-unique SKUs, category trees, and a seeder for test data.

## Installation

```bash
composer require markommerce/catalog
```

Scope override support is optional. To add scoped field storage to catalog entities, install the companion bridge package:

```bash
composer require markommerce/catalog-scope
```

For the ready-made storefront category route, product grid, and Latte templates, see [markommerce/catalog-storefront](https://markommerce.dev/docs/packages/catalog-storefront/).

For per-market category tree assignment and resolution, see [markommerce/catalog-market-category-trees](https://markommerce.dev/docs/packages/catalog-market/).

For a denormalized price index suitable for sorting and filtering, see [markommerce/catalog-price-index](https://markommerce.dev/docs/packages/catalog-price-index/).

For attribute-based layered navigation on category pages, see [markommerce/catalog-attribute-storefront](https://markommerce.dev/docs/packages/catalog-attribute-storefront/).

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Catalog\Services\ProductService;

// Create a product with a globally-unique SKU
$product = $productService->createProduct(sku: 'SKU-0001', name: 'Widget', description: 'A handy widget.');

// Create a category and place it in the default tree
$category = new Category();
$category->name = 'Widgets';
$categoryRepository->save($category);

$tree = $categoryTreeService->ensureDefaultTreeExists();
$categoryTreeService->placeCategory(treeId: $tree->id, categoryId: $category->id);
```

## Product List Filter Extension Point

`markommerce/catalog` ships a generic, attribute-agnostic extension point for narrowing the category product listing: `ProductListFilterInterface`, `ProductListFilterRegistry`, and `FilterSelection`.

Any package can contribute additional WHERE conditions to `CategoryAssignmentService::paginatedProductsInCategory()` by implementing `ProductListFilterInterface` and registering the implementation in `ProductListFilterRegistry` at boot.

### `FilterSelection`

A readonly value object carrying an `array<string, list<string>>` of active filter keys and their selected values.

| Method | Description |
|---|---|
| `forKey(string $key): list<string>` | Return the selected values for a key (empty array when not present) |
| `keys(): list<string>` | All active filter keys |
| `isEmpty(): bool` | Whether no filters are active |
| `without(string $key): self` | Return a new selection without the given key (used for disjunctive faceting) |

### `ProductListFilterInterface`

```php
<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Filtering\ProductListFilterInterface;

class MyCustomFilter implements ProductListFilterInterface
{
    public function apply(
        RepositoryQueryBuilder $repositoryQueryBuilder,
        FilterSelection $filterSelection,
    ): void {
        $values = $filterSelection->forKey('my_attribute');

        if ($values === []) {
            return; // no-op when key not active
        }

        // Add constraints to the product listing query
        $repositoryQueryBuilder->whereIn('my_column', $values);
    }
}
```

### `ProductListFilterRegistry`

Register your filter in your `module.php` boot closure:

```php title="module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Filtering\ProductListFilterRegistry;

return [
    'boot' => function (
        ProductListFilterRegistry $productListFilterRegistry,
        MyCustomFilter $myCustomFilter,
    ): void {
        $productListFilterRegistry->register($myCustomFilter);
    },
];
```

`CategoryAssignmentService::paginatedProductsInCategory()` accepts an optional `FilterSelection $selection` parameter (defaults to empty). It applies all registered filters before paginating, so the filter constraint is pushed into the SQL query — not applied in PHP after fetching.

The built-in `AttributeProductListFilter` from [markommerce/catalog-attribute-storefront](https://markommerce.dev/docs/packages/catalog-attribute-storefront/) uses this extension point to add EXISTS constraints over the `catalog_product_attribute_index` table for attribute-based layered navigation.

## Seeder

```bash
# Seed 5 000 products, categories, and a default tree
php artisan db:seed --seeder=catalog
```

The catalog seeder populates products with globally-unique SKUs, categories, product-category assignments, and places all seeded categories in the default category tree. Locale-scoped overrides are seeded separately by `markommerce/catalog-scope` (`php artisan db:seed --seeder=catalog-locale`).

## Documentation

Full usage, API reference, and examples: [markommerce/catalog](https://markommerce.dev/docs/packages/catalog/)
