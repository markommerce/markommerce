# markommerce/scope-pgsql

PostgreSQL driver for `markommerce/scope` — jsonb column support and scoped `ORDER BY`.

## Installation

```bash
composer require markommerce/scope-pgsql
```

Installs `markommerce/scope` automatically as a transitive dependency.

## Quick Example

```php
use App\Catalog\Entity\Product;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Query\ScopedOrderByFactory;

$scopeContext->in('locale', 'de-DE');

$products = $productRepository->matching(
    $scopedOrderByFactory->create(Product::class, 'name'),
);

// Emitted SQL:
// ORDER BY COALESCE(
//   "scopes"->'locale:de-DE'->>'name',
//   "scopes"->'locale:de'->>'name',
//   "name"
// ) ASC
```

## Documentation

Full usage, API reference, and examples: [markommerce/scope-pgsql](https://markommerce.dev/docs/packages/scope-pgsql/)
