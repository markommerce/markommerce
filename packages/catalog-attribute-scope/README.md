# markommerce/catalog-attribute-scope

Scoped product attribute value storage --- adds per-scope overrides to `Product` attribute values via a companion entity and a typed accessor that handles both JSON-backed and column-backed attributes.

## Installation

```bash
composer require markommerce/catalog-attribute-scope
```

## Quick Example

The package auto-wires itself at boot. `ProductScopedAttributeValues` is a companion that extends `catalog_products` via single-table inheritance and stores scoped attribute value overrides in a `scoped_attribute_values` JSON column. `ScopedProductAttributeAccessor` provides three operations:

- `setScoped(Product, code, value, ScopeSignature)` — validate and store a scoped override (rejects non-scopable attributes loudly)
- `getScoped(Product, code, ScopeSignature)` — read the raw override for a specific signature
- `resolve(Product, code)` — return the most-specific override for the active `ScopeContext`, falling back to the global value

```php
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Signature\ScopeSignature;

// Write a scoped value (validates/casts via the Phase-1 attribute validator)
$accessor->setScoped($product, 'description', 'Beschreibung', ScopeSignature::fromArray(['locale' => 'de']));

// Read the most-specific value for the active scope
$value = $accessor->resolve($product, 'description');
// Returns 'Beschreibung' when active locale is 'de', or the global value otherwise.
```

Column-backed (static) attributes are resolved via the generic `scope.ScopeResolver` over the native property. A scoped write to an unregistered native field throws loudly; install a native-field scoping bridge (catalog-scope + catalog-locale/-market) to enable it.

This package does NOT depend on `markommerce/catalog-scope`.

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-attribute-scope](https://markommerce.dev/docs/packages/catalog-attribute-scope/)
