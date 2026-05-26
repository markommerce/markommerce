# markommerce/catalog-scope

Scope storage bridge for catalog entities --- adds scoped override support to `Product` and `Category` via companion entities.

## Installation

```bash
composer require markommerce/catalog-scope
```

## Quick Example

The package auto-wires itself at boot. Once installed, catalog entities carry a `scopes` column that stores per-axis overrides:

```php
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Scope\Signature\ScopeSignature;

// ProductScopedOverrides extends catalog_products via single-table inheritance
// and implements HasScopesInterface — override resolution works out of the box.
$scopeResolver->setOverride(
    $product,
    'price',
    85.00,
    ScopeSignature::fromArray(['channel' => 'b2b']),
);

$price = $scopeResolver->resolved($product, 'price'); // 85.00
```

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-scope](https://markommerce.dev/docs/packages/catalog-scope/)
