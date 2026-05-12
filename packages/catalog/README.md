# markommerce/catalog

Product and category management for Markommerce — the foundation module that every e-commerce application starts with.

## Installation

```bash
composer require markommerce/catalog
```

This package depends on `markommerce/money` (interface only). Your application must also install a Money driver:

```bash
composer require markommerce/money-moneyphp   # default driver
```

## Quick Example

```php
use Markommerce\Catalog\Service\ProductServiceInterface;
use Markommerce\Catalog\Service\CategoryServiceInterface;
use Markommerce\Catalog\Service\CategoryAssignmentServiceInterface;
use Markommerce\Catalog\Service\ProductPriceServiceInterface;
use Markommerce\Money\MoneyFactoryInterface;

// All resolved from the container via this package's module.php bindings

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

Other markommerce modules should depend on a service (`*ServiceInterface`) for use cases. Direct repository access remains public for advanced callers and tests, but the service layer is the recommended cross-module API.

## Schema

Schema for `products`, `categories`, and `product_categories` is declared on the entity classes via `#[Table]` / `#[Column]` / `#[Index]` / `#[ForeignKey]` attributes. Run Marko's `db:migrate` in the consuming application to generate and apply migrations; **this package ships no SQL files**.

## Money and Prices

Products store only a minor-unit integer (`basePriceAmount`); they do not store currency and have no Money accessor. Use `ProductPriceServiceInterface::getBasePrice(Product)` to resolve a `MoneyInterface`. This is the seam where future discount, tax, and store-currency logic will plug in via Marko Preferences.

## Multi-store

`Product::$name`, the effective currency (resolved by `ProductPriceService`), and `Category::$name` are global today. The future stores/config module will scope them per store. Look for `@todo multi-store` docblocks in the source to find every site that will change.

## Documentation

Full documentation: [markommerce/catalog](https://markommerce.dev/docs/packages/catalog/)
