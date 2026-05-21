# markommerce/catalog

Products and categories for Markommerce --- locale-scoped names, globally-unique SKUs, and a ready-made storefront route.

## Installation

```bash
composer require markommerce/catalog
```

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Services\ProductService;

// Create a product with a globally-unique sku
$product = $productService->createProduct(sku: 'SKU-0001', name: 'Widget', description: 'A handy widget.');
$product->setOverride('locale:de', 'name', 'Widget DE');
$product->setOverride('locale:fr', 'name', 'Widget FR');
$productRepository->save($product);

// Create a category
$category = new Category();
$category->name = 'Widgets';
$category->setOverride('locale:de', 'name', 'Widgets DE');
$category->setOverride('locale:fr', 'name', 'Widgets FR');
$categoryRepository->save($category);

// Assign the product to the category
$categoryAssignmentService->assign($product->id, $category->id);
```

## Storefront

`GET /catalog/category/{id}` lists all products in a category using the active locale via `ScopeResolver`.

## Seeder

Run the catalog seeder to generate fake products, categories, and assignments (including `locale:de` / `locale:fr` overrides):

```bash
php bin/marko db:seed catalog
```

Note: locale overrides only resolve after those locales are registered in `config/scope.php`.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog](https://markommerce.dev/docs/packages/catalog/)
