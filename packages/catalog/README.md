# markommerce/catalog

Product and category management for Markommerce — the foundation module that every e-commerce application starts with.

## Installation

```bash
composer require markommerce/catalog
```

## Quick Example

```php
use Markommerce\Catalog\Service\CategoryAssignmentServiceInterface;
use Markommerce\Catalog\Service\CategoryServiceInterface;
use Markommerce\Catalog\Service\ProductPriceServiceInterface;
use Markommerce\Catalog\Service\ProductServiceInterface;
use Markommerce\Money\MoneyFactoryInterface;

$price = $moneyFactory->create(4999); // USD 49.99

$product = $productService->create(
    sku: 'WIDGET-42',
    name: 'Widget',
    basePrice: $price,
);

$category = $categoryService->create('Gadgets');
$assignmentService->assign($product->id, $category->id);

$displayPrice = $priceService->getBasePrice($product);
echo $displayPrice->format('en_US'); // "$49.99"
```

## Documentation

Full documentation: [markommerce/catalog](https://markommerce.dev/docs/packages/catalog/)
