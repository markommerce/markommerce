---
title: markommerce/pricing
description: Pricing pipeline for Markommerce — resolves a product's effective price as a Money value object, with a Plugin decoration seam for layering sale prices, tier pricing, and promotional overrides.
---

Pricing pipeline for Markommerce. The pricing classes live in `markommerce/catalog` under the `Markommerce\Catalog\Pricing` namespace. `PriceResolverInterface` resolves a product's effective price as a `Money` value object. The base implementation reads `Product.priceAmount` via `ProductBasePriceProviderInterface` (honoring the market scope via `ScopedProductBasePriceProvider` when `markommerce/catalog-market` is installed) and pairs the amount with the active base currency from `CurrencyResolver`. `PriceResolverInterface` is the primary Marko `#[Plugin]` seam for layering additional pricing concerns --- sale prices, tier prices, customer-group discounts, and promotional overrides --- without modifying the core implementation.

Tax mode is resolved independently via `markommerce/tax`; the pricing pipeline has no dependency on `tax`.

## Installation

```bash
composer require markommerce/catalog
```

The package binds `PriceResolverInterface`, `BatchPriceResolverInterface`, and `ProductBasePriceProviderInterface` automatically via `module.php`.

## Usage

### Building a price context

`PriceContext` describes what is being priced. Construct it with the static factory `forProduct()`:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\PriceContext;

// Global price (no market context)
$context = PriceContext::forProduct($product);

// Price in a specific market
$context = PriceContext::forProduct($product, 'us');
```

> **Market-scoped resolution** --- for per-market price resolution to work, the `Product` inside `PriceContext` must carry its `ProductScopedOverrides` companion (i.e. it was loaded via the repository with extenders linked). A bare `new Product()` resolves only the global `priceAmount`.

### Resolving the effective price

Inject `PriceResolverInterface` and call `resolve()`:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;

// $priceResolver is injected by the container
try {
    $money = $priceResolver->resolve($context);
} catch (PriceUnavailableException $e) {
    // No priceAmount set on the product (or its market override)
}

echo $money->amount();           // e.g. "29.99"
echo $money->currency()->code;   // e.g. "USD"
```

`PriceUnavailableException` is thrown when neither the global `priceAmount` nor any market-scoped override contains a value.

### Layering pricing rules via Plugins

Every pricing concern --- sale prices, tier pricing, customer-group discounts, promotional overrides --- decorates `PriceResolverInterface::resolve()` via a Marko `#[Plugin]` rather than forking the core. Use `around` to intercept the call and modify the result:

```php
<?php

declare(strict_types=1);

use Marko\Core\Attributes\Plugin;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Money\Money;

#[Plugin(plugs: PriceResolverInterface::class, method: 'resolve')]
class SalePricePlugin
{
    public function aroundResolve(
        PriceResolverInterface $subject,
        callable $proceed,
        PriceContext $context,
    ): Money {
        $money = $proceed($context);
        // apply sale price logic here — return modified Money or the original
        return $money;
    }
}
```

Plugins are stacked --- you can have a `SalePricePlugin`, a `TierPricePlugin`, and a `PromotionalPlugin` all decorating the same resolver, each applied in declaration order.

## API Reference

### `PriceContext`

Readonly value object. Constructed via the static factory only.

| Property | Type | Description |
|---|---|---|
| `$product` | `Product` | The product being priced. Must carry `ProductScopedOverrides` for market-scoped resolution. |
| `$market` | `?string` | Optional market identifier. When `null`, global `priceAmount` is used. |

| Method | Return type | Description |
|---|---|---|
| `PriceContext::forProduct(Product $product, ?string $market = null)` | `PriceContext` | Construct a price context for a product, optionally scoped to a market. |

### `PriceResolverInterface`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `resolve(PriceContext $context)` | `Money` | `PriceUnavailableException` | Resolve the effective price for the given context as a `Money` value. |

`PriceResolver` is the default implementation. Override it with a Marko Preference or decorate it with `#[Plugin]`.

### Exceptions

| Exception | Named constructor | When thrown |
|---|---|---|
| `PriceUnavailableException` | `forContext(PriceContext $context)` | No resolvable price amount found for the product in the given market. The exception `context` includes the SKU and market identifier. |

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- `Product` entity, `priceAmount` column, and the full pricing pipeline including `BatchPriceResolverInterface` and `PriceContributorInterface`
- [markommerce/money](/docs/packages/money/) --- `Money` value object returned by the resolver
- [markommerce/currency](/docs/packages/currency/) --- `CurrencyResolver` used to pair the resolved amount with the active currency
- [markommerce/catalog-market](/docs/packages/catalog-market/) --- Registers `Product.priceAmount` on the `market` axis and overrides `ProductBasePriceProviderInterface` with `ScopedProductBasePriceProvider` for per-market price resolution
- [markommerce/catalog-price-index](/docs/packages/catalog-price-index/) --- Denormalized price index populated by the `BatchPriceResolverInterface` pipeline
- [markommerce/tax](/docs/packages/tax/) --- Independent tax mode resolution; not a dependency of the pricing pipeline
