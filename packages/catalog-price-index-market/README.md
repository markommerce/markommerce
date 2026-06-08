# markommerce/catalog-price-index-market

Market-axis bridge for the product price index --- registers the index `amount` on the `market` axis and feeds the indexer the configured market list so it writes per-market price amounts into the `scopes` JSON column.

## Installation

```bash
composer require markommerce/catalog-price-index-market
```

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Resolver\ScopeResolver;

// After installing this bridge, the indexer writes per-market amounts.
// Read them back via ScopeResolver under an active market context:
$entry = $productPriceIndexRepository->findByProductId(42);

$scopeContext->in('market', 'us');
$amount = $scopeResolver->resolved($entry, 'amount'); // market-scoped or base fallback
```

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-price-index](https://markommerce.dev/docs/packages/catalog-price-index/)
