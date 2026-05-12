---
title: markommerce/catalog
description: Product and category management for Markommerce — the foundation module that every e-commerce application starts with.
---

Product and category management for Markommerce — the foundation module that every e-commerce application starts with. This package defines the `Product` and `Category` entities, their persistence schema, and four service interfaces that form the primary cross-module API for catalog operations.

## Installation

```bash
composer require markommerce/catalog
```

This package depends on `markommerce/money` (interface only). Your application must also install a Money driver:

```bash
composer require markommerce/money-moneyphp   # default driver
```

## Usage

All services are resolved from the container via this package's `module.php` bindings.

```php
use Markommerce\Catalog\Service\CategoryAssignmentServiceInterface;
use Markommerce\Catalog\Service\CategoryServiceInterface;
use Markommerce\Catalog\Service\ProductPriceServiceInterface;
use Markommerce\Catalog\Service\ProductServiceInterface;
use Markommerce\Money\MoneyFactoryInterface;

// 1. Create a product
$price = $moneyFactory->create(4999); // USD 49.99

$product = $productService->create(
    sku: 'WIDGET-42',
    name: 'Widget',
    basePrice: $price,
);

// 2. Create a category and assign the product
$category = $categoryService->create('Gadgets');
$assignmentService->assign($product->id, $category->id);

// 3. Fetch by SKU
$product = $productService->getBySku('WIDGET-42');

// 4. Resolve the display price (never read basePriceAmount directly)
$displayPrice = $priceService->getBasePrice($product);
echo $displayPrice->format('en_US'); // "$49.99"
```

## Service vs. Repository

Other Markommerce modules should depend on a service (`*ServiceInterface`) for use cases. Direct repository access remains public for advanced callers and tests, but the service layer is the recommended cross-module API.

## Schema

Schema for `products`, `categories`, and `product_categories` is declared on the entity classes via `#[Table]` / `#[Column]` / `#[Index]` / `#[ForeignKey]` attributes. Run Marko's `db:migrate` in the consuming application to generate and apply migrations — **this package ships no SQL files**.

## Money and Prices

Products store only a minor-unit integer (`basePriceAmount`); they do not store currency and have no Money accessor. Use `ProductPriceServiceInterface::getBasePrice(Product)` to resolve a `MoneyInterface`. This is the seam where future discount, tax, and store-currency logic will plug in via Marko Preferences.

## Multi-store

`Product::$name`, the effective currency (resolved by `ProductPriceService`), and `Category::$name` are global today. The future stores/config module will scope them per store. Look for `@todo multi-store` docblocks in the source to find every site that will change.

## API Reference

### `ProductServiceInterface`

| Method | Signature | Description |
|--------|-----------|-------------|
| `create` | `create(string $sku, string $name, MoneyInterface $basePrice): Product` | Creates and persists a new product. Throws `InvalidProductDataException` or `DuplicateSkuException`. |
| `get` | `get(int $id): ?Product` | Returns a product by ID, or `null` if not found. |
| `getBySku` | `getBySku(string $sku): ?Product` | Returns a product by SKU, or `null` if not found. |
| `update` | `update(Product $product): Product` | Persists changes to an existing product. Throws `InvalidProductDataException` or `DuplicateSkuException`. |
| `delete` | `delete(int $id): void` | Removes a product by ID. Throws `ProductNotFoundException`. |
| `list` | `list(): array` | Returns all products as `array<Product>`. |

### `CategoryServiceInterface`

| Method | Signature | Description |
|--------|-----------|-------------|
| `create` | `create(string $name): Category` | Creates and persists a new category. Throws `InvalidCategoryDataException`. |
| `get` | `get(int $id): ?Category` | Returns a category by ID, or `null` if not found. |
| `list` | `list(): array` | Returns all categories as `array<Category>`. |
| `update` | `update(Category $category): Category` | Persists changes to an existing category. Throws `InvalidCategoryDataException` or `CategoryNotFoundException`. |
| `delete` | `delete(int $id): void` | Removes a category by ID. Throws `CategoryNotFoundException`. |

### `CategoryAssignmentServiceInterface`

| Method | Signature | Description |
|--------|-----------|-------------|
| `assign` | `assign(int $productId, int $categoryId): void` | Assigns a product to a category. Throws `ProductNotFoundException` or `CategoryNotFoundException`. |
| `unassign` | `unassign(int $productId, int $categoryId): void` | Removes a product–category assignment. |
| `getCategoriesForProduct` | `getCategoriesForProduct(int $productId): array` | Returns `array<Category>` for a product. Throws `ProductNotFoundException`. |
| `getProductsInCategory` | `getProductsInCategory(int $categoryId): array` | Returns `array<Product>` in a category. Throws `CategoryNotFoundException`. |

### `ProductPriceServiceInterface`

| Method | Signature | Description |
|--------|-----------|-------------|
| `getBasePrice` | `getBasePrice(Product $product): MoneyInterface` | Resolves the product's `basePriceAmount` into a `MoneyInterface` using the configured default currency. This is the extension point for discount and tax logic via Marko Preferences. |

### `Product` Entity

Mapped to the `products` table.

| Property | Type | Description |
|----------|------|-------------|
| `$id` | `?int` | Auto-increment primary key. |
| `$sku` | `string` | Unique SKU (max 255 chars). |
| `$name` | `string` | Display name (max 255 chars). Global today — will be store-scoped. |
| `$basePriceAmount` | `int` | Price in minor units (e.g. cents). No currency stored on the entity — use `ProductPriceServiceInterface::getBasePrice()`. |
| `$categories` | `array<Category>` | Resolved via `BelongsToMany` through the `product_categories` pivot. |

### `Category` Entity

Mapped to the `categories` table.

| Property | Type | Description |
|----------|------|-------------|
| `$id` | `?int` | Auto-increment primary key. |
| `$name` | `string` | Display name (max 255 chars). Global today — will be store-scoped. |

## Related Packages

- [markommerce/money](/docs/packages/money/) — contract package for monetary values
- [markommerce/money-moneyphp](/docs/packages/money-moneyphp/) — default Money driver
