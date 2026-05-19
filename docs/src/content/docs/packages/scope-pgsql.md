---
title: markommerce/scope-pgsql
description: PostgreSQL driver for markommerce/scope — jsonb column support, scoped ORDER BY, and auto GIN index.
---

PostgreSQL driver for `markommerce/scope` --- adds `jsonb` column support, scoped `ORDER BY`, and an automatically emitted `jsonb_path_ops` GIN index for PostgreSQL-backed applications. The `scopes` column is declared by the `HasScopes` trait on the entity. The `json` column type materialises as `JSONB` in PostgreSQL via the driver's type map, giving full indexed JSON support without any extra configuration.

## Installation

```bash
composer require markommerce/scope-pgsql
```

This automatically installs `markommerce/scope` as a transitive dependency.

## Usage

### Adding the `scopes` column

Implement `HasScopesInterface` and use the `HasScopes` trait on the entity. The trait declares the `scopes` JSONB column directly; no companion class is needed:

```php title="app/catalog/Entity/Product.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table('products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    #[Scoped(axes: ['channel', 'locale'])]
    public string $name = '';
}
```

Register `Product` with the `SchemaRegistry`. The `scopes` column will appear in the `products` table after the next migration run.

### Auto GIN Index

A `jsonb_path_ops` GIN index named `<table>_scopes_gin` (e.g. `products_scopes_gin`) is emitted automatically via a side-channel `CREATE INDEX IF NOT EXISTS` statement for every table that uses `HasScopes`. No manual index creation or migration is needed.

### Scoped ORDER BY

Pass a `ScopedOrderBy` specification to `Repository::matching`. `PgSqlScopedFieldRenderer` emits a COALESCE chain over composite-signature JSONB keys in descending-score order, falling back to a plain column sort when no scope path is active.

`SignatureCandidateEnumerator` generates all candidate signatures for multi-axis resolution. The default candidate cap is **256** --- if the enumerated candidate count exceeds 256 the enumerator emits an `E_USER_WARNING` and truncates the candidate list. You can increase the cap by constructing `SignatureCandidateEnumerator` with a custom `$cap` value, but very large caps can produce impractically long `COALESCE` expressions.

Two-axis composite example (channel + locale):

```php title="app/catalog/Repository/ProductRepository.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Product;
use Marko\Database\Repository\Repository;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Query\ScopedOrderBy;
use Markommerce\Scope\Query\ScopedOrderByFactory;

class ProductRepository extends Repository
{
    protected const ENTITY_CLASS = Product::class;

    public function __construct(
        private ScopedOrderByFactory $scopedOrderByFactory,
        private ScopeContext $scopeContext,
    ) {}

    public function listByName(): array
    {
        $this->scopeContext->in('channel', 'b2b');
        $this->scopeContext->in('locale', 'de-DE');

        return $this->matching(
            $this->scopedOrderByFactory->create(Product::class, 'name'),
        );
    }
}
```

Emitted SQL (composite-signature COALESCE chain, descending-score order):

```sql
ORDER BY COALESCE(
    "scopes"->'channel:b2b|locale:de-DE'->>'name',
    "scopes"->'channel:b2b|locale:de'->>'name',
    "scopes"->'channel:b2b'->>'name',
    "scopes"->'locale:de-DE'->>'name',
    "scopes"->'locale:de'->>'name',
    "name"
) ASC
```

## Running Integration Tests

The integration test suite exercises real PostgreSQL behaviour (GIN index creation, COALESCE query emission). These tests are marked as destructive and are excluded from the default `composer test` run.

To run the full suite including integration tests, use `composer test:all` inside the `marko-playground-app` Docker container:

```bash
docker compose -f ~/www/marko/compose.yaml exec -w /workspace/markommerce app composer test:all
```

The compose Postgres service must be running. The tests create and tear down their own schema; they do not modify any existing data.

## Not Shipped: ScopedSelect and ScopedWhere

`ScopedSelect` and `ScopedWhere` are **not included** in this release. They require `selectRaw` / `whereRaw` support on `marko/database`'s `QueryBuilderInterface`, which does not exist today. These features are deferred to a follow-up plan once `marko/database` exposes the necessary raw-expression entry points.

## API Reference

### `PgSqlScopedFieldRenderer`

Implements `ScopedFieldRendererInterface`.

| Method | Description |
|--------|-------------|
| `render(ScopedFieldExpression $expression): string` | Renders a `ScopedFieldExpression` as a PostgreSQL `COALESCE("scopes"->'key'->>'property', column)` fragment. Throws `InvalidColumnException` if any identifier in the expression is invalid. |

## Related Packages

- [markommerce/scope](/docs/packages/scope/) --- Core scoped attributes package
- [marko/database-pgsql](https://marko.build/docs/packages/database-pgsql/) --- PostgreSQL database driver
