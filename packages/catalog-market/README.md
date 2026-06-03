# markommerce/catalog-market

Market integration for catalog entities --- per-market category tree assignment and resolution, a deletion guard plugin, and per-market product price overrides via the `ScopedFieldRegistry` boot hook.

## Installation

```bash
composer require markommerce/catalog-market
```

## Quick Example

```php
use Marko\Config\ConfigRepository;
use Marko\Core\Container\Container;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Resolver\ScopeResolver;

// After booting, Product.priceAmount is registered on the market axis:
// $scopedFieldRegistry->register(Product::class, 'priceAmount', ['market']);

// Set a per-market price override
$product = new Product();
$product->priceAmount = '99.9900';

$overrides = new ProductScopedOverrides();
$overrides->setOverride('market:us', 'priceAmount', '79.9900');
$product->attachCompanion($overrides);

// Resolve the price for the active market context
$scopeContext->in('market', 'us');
$price = $scopeResolver->resolved($product, 'priceAmount'); // '79.9900'

// Falls back to global price when no market override exists
$scopeContext->in('market', 'default');
$price = $scopeResolver->resolved($product, 'priceAmount'); // '99.9900'

// Assign a category tree to a market
$categoryTreeMarketAssignmentService->assignTreeToMarket($euTree->id, 'market:eu-de');

// Resolve the active tree for a market (falls back to the default tree)
$tree = $categoryTreeMarketResolver->resolveTreeForMarket('market:eu-de');
```

## What This Package Does

- Registers `Product.priceAmount` on the `market` axis via `ScopedFieldRegistry`, enabling per-market base prices that fall back to the global product price when no override is set.
- Provides `CategoryTreeMarketAssignmentService` and `CategoryTreeMarketResolver` for per-market category tree assignment and resolution.
- Includes a `CategoryTreeServiceDeletePlugin` that guards against deleting a category tree that is still assigned to a market.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-market](https://markommerce.dev/docs/packages/catalog-market/)
