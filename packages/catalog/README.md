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

## Seeder

```bash
# Seed 5 000 products, categories, and a default tree
php artisan db:seed --seeder=catalog
```

The catalog seeder populates products with globally-unique SKUs, categories, product-category assignments, and places all seeded categories in the default category tree. Locale-scoped overrides are seeded separately by `markommerce/catalog-scope` (`php artisan db:seed --seeder=catalog-locale`).

## Documentation

Full usage, API reference, and examples: [markommerce/catalog](https://markommerce.dev/docs/packages/catalog/)
