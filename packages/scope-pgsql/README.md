# markommerce/scope-pgsql

PostgreSQL driver for `markommerce/scope` — jsonb column support, scoped `ORDER BY`, and auto-emitted GIN index.

## Installation

```bash
composer require markommerce/scope-pgsql
```

Installs `markommerce/scope` automatically as a transitive dependency.

## Quick Example

Two-axis composite: channel + locale scoped `ORDER BY`:

```php
use App\Catalog\Entity\Product;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Query\ScopedOrderByFactory;

$scopeContext->in('channel', 'b2b');
$scopeContext->in('locale', 'de-DE');

$products = $productRepository->matching(
    $scopedOrderByFactory->create(Product::class, 'name'),
);

// Emitted SQL (composite-signature COALESCE chain, descending score):
// ORDER BY COALESCE(
//   "scopes"->'channel:b2b|locale:de-DE'->>'name',
//   "scopes"->'channel:b2b|locale:de'->>'name',
//   "scopes"->'channel:b2b'->>'name',
//   "scopes"->'locale:de-DE'->>'name',
//   "scopes"->'locale:de'->>'name',
//   "name"
// ) ASC
```

## Auto GIN Index

A `jsonb_path_ops` GIN index is emitted automatically via a side-channel `CREATE INDEX IF NOT EXISTS` statement for every table that uses `HasScopes`. The index follows the naming pattern `<table>_scopes_gin` (e.g. `products_scopes_gin`). No manual index creation is needed.

## Not Shipped in This Release

`ScopedSelect` and `ScopedWhere` are **not included** in this release. They require `selectRaw` / `whereRaw` support on `marko/database`'s `QueryBuilderInterface`, which does not exist today. These features are deferred to a follow-up plan.

## Documentation

Full usage, API reference, and examples: [markommerce/scope-pgsql](/docs/packages/scope-pgsql/)
