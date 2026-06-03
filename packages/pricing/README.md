# markommerce/pricing

Pricing pipeline for Markommerce --- resolves a product's effective price as a `Money` value object, with a `#[Plugin]` decoration seam for layering sale prices, tier prices, customer-group discounts, and promotional overrides.

## Installation

```bash
composer require markommerce/pricing
```

## Quick Example

```php
use Markommerce\Catalog\Entity\Product;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\PriceContext;

// Build a price context for a product (optionally scoped to a market)
$context = PriceContext::forProduct($product);           // global price
$context = PriceContext::forProduct($product, 'us');     // US-market price

// Resolve the effective price as Money
/** @var PriceResolverInterface $resolver */
$money = $resolver->resolve($context);

echo $money->amount();           // e.g. "29.99"
echo $money->currency()->code;   // e.g. "USD"
```

The base resolver reads `Product.priceAmount` via `ScopeResolver` (honoring the market scope through the `ProductScopedOverrides` companion) and pairs it with the active base currency from `CurrencyResolver`.

> **Note:** For per-market price resolution to work correctly, the `Product` inside `PriceContext` must carry its `ProductScopedOverrides` companion (i.e. it was loaded via the repository with extenders linked). A bare `new Product()` will only resolve the global `priceAmount`.

## Plugin Decoration Seam

`PriceResolverInterface` is the primary `#[Plugin]` seam for the pricing pipeline. Every pricing concern --- sale prices, tier pricing, customer-group discounts, promotional rules --- decorates the resolver via Marko plugins rather than forking the core implementation:

```php
use Marko\Core\Attributes\Plugin;
use Markommerce\Money\Money;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\PriceContext;

#[Plugin(plugs: PriceResolverInterface::class, method: 'resolve')]
class SalePricePlugin
{
    public function aroundResolve(
        PriceResolverInterface $subject,
        callable $proceed,
        PriceContext $context,
    ): Money {
        $money = $proceed($context);
        // apply sale price logic here...
        return $money;
    }
}
```

## Documentation

Full usage, API reference, and examples: [markommerce/pricing](https://markommerce.dev/docs/packages/pricing/)
