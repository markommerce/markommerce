# markommerce/catalog

Products and categories for Markommerce --- locale-scoped names, globally-unique SKUs, per-market category trees, and a ready-made storefront route.

## Installation

```bash
composer require markommerce/catalog
```

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Enum\NodeRemovalStrategy;
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

// Resolve the active tree for a market
$activeTree = $categoryTreeService->resolveTreeForMarket('market:eu');
```

## Storefront Route

`GET /catalog/category/{id}` — returns the category and its products. Registered automatically by the module.

## Seeder

```bash
# Seed 5 000 products, categories, and a default tree (locale:de and locale:fr overrides included)
php artisan db:seed --class=catalog
```

The catalog seeder populates products with globally-unique SKUs, categories with locale-scoped names (`locale:de`, `locale:fr`), product-category assignments, and places all seeded categories in the default category tree.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog](https://markommerce.dev/docs/packages/catalog/)
