---
title: markommerce/criteria
description: Headless, entity-agnostic pagination engine for Markommerce — sorting, keyset and offset strategies, position tokens, and row counting.
---

Headless, entity-agnostic pagination engine for Markommerce. `markommerce/criteria` operates directly on `marko/database` `RepositoryQueryBuilder` and `EntityCollection` instances. It knows nothing about HTTP, HTML, or any specific domain entity: it receives a query builder, applies ordering and windowing, and returns a typed `Page` value object. Storefront routes, URL query-string binding, and Latte template helpers live in `markommerce/catalog` and `markommerce/catalog-storefront`.

## Installation

```bash
composer require markommerce/criteria
```

The package declares itself as a `marko-module` and registers its default strategy and counter bindings automatically via `module.php`. No manual service binding is required.

## Usage

### Building a page request

A `PageRequest` is immutable and constructed via named static constructors. Use `PageRequest::first()` for the first page and `PageRequest::at()` for any subsequent page identified by an opaque position token:

```php
<?php

declare(strict_types=1);

use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

$sort = new Sort(
    new SortField(column: 'name'),
    new SortField(column: 'created_at', direction: SortDirection::Descending),
);

$first = PageRequest::first(size: 24, sort: $sort);
$next  = PageRequest::at(size: 24, sort: $sort, position: $page->nextPosition);
```

`InvalidPageSizeException` is thrown when `$size <= 0`. An `EmptySortException` is thrown when `Sort` is constructed with no fields.

### Paginating with the default (keyset) strategy

`PaginationStrategyInterface` is the single entry point for all pagination. Inject it into your service or repository class --- the default binding resolves to `KeysetPaginationStrategy`:

```php
<?php

declare(strict_types=1);

use Markommerce\Criteria\Contracts\CursorValueExtractorInterface;
use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortField;

// $paginationStrategy is PaginationStrategyInterface (keyset by default).
$sort        = new Sort(new SortField(column: 'name'));
$pageRequest = PageRequest::first(size: 24, sort: $sort);

$page = $paginationStrategy->paginate($query, $pageRequest, $cursorValueExtractor);

foreach ($page->items as $entity) { /* ... */ }

if ($page->hasNext()) {
    $next     = PageRequest::at(size: 24, sort: $sort, position: $page->nextPosition);
    $nextPage = $paginationStrategy->paginate($query, $next, $cursorValueExtractor);
}
```

### Offset pagination (numbered pages, jump-to-page)

Use `OffsetPaginationStrategy` directly when the UI needs numbered pages or jump-to-page. This strategy returns an `OffsetPage`, which implements `RandomAccessPageInterface`:

```php
<?php

declare(strict_types=1);

use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Counter\ExactRowCounter;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Criteria\Strategy\OffsetPaginationStrategy;

$positionCodec = new PositionCodec();
$strategy      = new OffsetPaginationStrategy($positionCodec, new ExactRowCounter());

$sort        = new Sort(new SortField(column: 'name'));
$pageRequest = PageRequest::first(size: 24, sort: $sort);

$page = $strategy->paginate($query, $pageRequest);

echo $page->currentPage();  // 1
echo $page->totalPages();   // e.g. 12
echo $page->totalItems();   // e.g. 275

// Jump to page 5.
$token = $page->positionForPage(5);
$page5 = $strategy->paginate($query, PageRequest::at(24, $sort, $token));
```

**Important:** `ExactRowCounter` delegates to `RepositoryQueryBuilder::count()`, which drops JOIN clauses. For queries that filter rows via a JOIN (for example, catalog's category membership join), supply a join-safe counter. See [Consumer Integration](#consumer-integration).

### Keyset pagination (cursor-based, infinite scroll)

`KeysetPaginationStrategy` implements seek-based pagination using a PostgreSQL row-value comparison (`WHERE (sort_col, id) > (?, ?)`). It is sequential only --- `previousPosition` is always `null`. Numbered pagination UI and jump-to-page require `OffsetPaginationStrategy` instead.

To use keyset pagination, implement `CursorValueExtractorInterface` and pass it to `paginate()`:

```php
<?php

declare(strict_types=1);

use Markommerce\Criteria\Contracts\CursorValueExtractorInterface;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

$extractor = new class implements CursorValueExtractorInterface {
    public function extract(object $entity, Sort $sort): array
    {
        return ['name' => $entity->name];
    }
};

$positionCodec = new PositionCodec();
$strategy      = new KeysetPaginationStrategy($positionCodec);

$sort        = new Sort(new SortField(column: 'name'));
$pageRequest = PageRequest::first(size: 24, sort: $sort);

$page = $strategy->paginate($query, $pageRequest, $extractor);

// $page->nextPosition is the opaque token for the next page.
// $page->previousPosition is always null for keyset pagination.
```

`MissingCursorValueExtractorException` is thrown if the strategy needs an extractor but none was supplied (i.e. on page 2+).

### Consumer Integration

The following example shows how a catalog repository selects offset pagination, supplies a join-safe counter, and paginates a product list:

```php
<?php

declare(strict_types=1);

use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Criteria\Strategy\OffsetPaginationStrategy;

// Build a join-safe counter for category-filtered product queries.
// ExactRowCounter is not join-aware; wrap a subquery-based counter instead.
$joinSafeCounter = new class implements RowCounterInterface {
    public function count(\Marko\Database\Repository\RepositoryQueryBuilder $query): int
    {
        // Execute a join-aware count query here.
        return $query->countViaSubquery(); // illustrative
    }
};

$positionCodec = new PositionCodec();
$strategy      = new OffsetPaginationStrategy($positionCodec, $joinSafeCounter);

$sort        = new Sort(new SortField(column: 'name'));
$pageRequest = PageRequest::first(size: 24, sort: $sort);

$page = $strategy->paginate($query, $pageRequest);

echo $page->totalItems(); // join-safe total
```

## API Reference

### Core Value Objects

#### `Sort` / `SortField` / `SortDirection` / `NullsPlacement`

A `Sort` wraps one or more `SortField` instances. Each `SortField` carries a column name, a `SortDirection` enum case (`Ascending` or `Descending`, defaulting to `Ascending`), an optional raw SQL `$expression` override, and an optional `NullsPlacement` hint. Constructing `Sort` with no fields throws `EmptySortException` immediately.

`SortField` constructor parameters:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$column` | `string` | required | Column identifier; validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/` before SQL interpolation |
| `$direction` | `SortDirection` | `Ascending` | Sort direction |
| `$expression` | `?string` | `null` | Raw SQL expression to use in the ORDER BY clause instead of the column name; when non-null, `sortExpression()` returns this value. **Only use with code-defined, non-user-derived strings.** |
| `$nulls` | `?NullsPlacement` | `null` | `NullsPlacement::First` or `NullsPlacement::Last`; `null` means the database default applies. Implemented via a companion `(expr) IS NULL ASC/DESC` clause rather than SQL `NULLS FIRST/LAST` for cross-database compatibility. |

`SortField::sortExpression()` returns `$expression` when set, otherwise `$column`.

`NullsPlacement` is a backed enum with two cases:

| Case | Description |
|---|---|
| `First` | Sort NULL values before non-NULL values |
| `Last` | Sort NULL values after non-NULL values |

#### `PageRequest`

| Named constructor | Description |
|---|---|
| `PageRequest::first(int $size, Sort $sort)` | First page (no position token). Throws `InvalidPageSizeException` if `$size <= 0`. |
| `PageRequest::at(int $size, Sort $sort, string $position)` | Subsequent page identified by an opaque position token. |

| Property | Type | Description |
|---|---|---|
| `$size` | `int` | Requested page size |
| `$sort` | `Sort` | Sort specification |
| `$position` | `?string` | Opaque position token, or `null` for the first page |

#### `Page`

The result of any `paginate()` call.

| Property / Method | Type | Description |
|---|---|---|
| `$items` | `EntityCollection` | Hydrated entities for this page |
| `$size` | `int` | Requested page size |
| `$nextPosition` | `?string` | Opaque token for the next page, or `null` |
| `$previousPosition` | `?string` | Opaque token for the previous page, or `null` |
| `hasNext()` | `bool` | Whether a next page exists |
| `hasPrevious()` | `bool` | Whether a previous page exists |

### Contracts

#### `PaginationStrategyInterface`

```php
public function paginate(
    RepositoryQueryBuilder $query,
    PageRequest $pageRequest,
    ?CursorValueExtractorInterface $cursorValueExtractor = null,
): Page;
```

#### `RowCounterInterface`

```php
public function count(RepositoryQueryBuilder $query): int;
```

#### `CursorValueExtractorInterface`

```php
/** @return array<string, scalar> */
public function extract(object $entity, Sort $sort): array;
```

#### `RandomAccessPageInterface`

Implemented by `OffsetPage`. Exposes the page number and total metadata needed for numbered pagination UI.

| Method | Return type | Throws | Description |
|---|---|---|---|
| `currentPage()` | `int` | --- | Current page number (1-based) |
| `totalPages()` | `int` | --- | Total page count |
| `totalItems()` | `int` | --- | Total matching rows |
| `positionForPage(int $page)` | `string` | `PageOutOfRangeException` | Opaque position token for any valid page number in `[1, totalPages]` |

### Strategies

#### `KeysetPaginationStrategy` (engine default)

Seek-based pagination via a PostgreSQL row-value comparison. Sequential only --- `previousPosition` is always `null`. Requires `CursorValueExtractorInterface` on pages after the first. Rejects `OffsetPosition` tokens with `IncompatiblePositionException`. Appends `ORDER BY id ASC` after caller-supplied sort fields as a deterministic tie-break.

#### `OffsetPaginationStrategy` (opt-in for random access)

Classic `LIMIT`/`OFFSET` pagination. Returns `OffsetPage` which implements `RandomAccessPageInterface`. Requires a `RowCounterInterface` to compute `totalPages`. Rejects `KeysetPosition` tokens with `IncompatiblePositionException`.

### Row Counters

#### `ExactRowCounter`

Delegates to `RepositoryQueryBuilder::count()`. Correct for single-table queries. Not join-aware --- see the note in [Offset Pagination](#offset-pagination-numbered-pages-jump-to-page).

#### `EstimatedRowCounter`

Uses a planner/catalog estimate for unfiltered queries and falls back to the exact counter when the query has active WHERE filters or when the injected `CountEstimateSourceInterface` returns `null`.

| Dependency | Role |
|---|---|
| `RowCounterInterface $rowCounter` | Fallback exact counter |
| `CountEstimateSourceInterface $countEstimateSource` | Provides planner estimates |
| `QueryFilterDetectorInterface $queryFilterDetector` | Detects active WHERE filters |

> `CachedRowCounter` is planned but not yet built. Do not reference it in production code.

### Position Tokens

Position tokens are opaque, versioned base64url strings. Consumers must treat them as black boxes --- never parse, construct, or compare them directly. The codec validates the `v` (version) field on decode and throws `InvalidPositionTokenException` for unsupported versions or malformed payloads. Passing a keyset token to `OffsetPaginationStrategy` (or vice versa) throws `IncompatiblePositionException`.

`PositionCodec` handles encoding and decoding:

```php
<?php

declare(strict_types=1);

use Markommerce\Criteria\Position\PositionCodec;

$codec = new PositionCodec();

// Encode (returns a base64url string).
$token = $codec->encode($offsetPosition);  // or $keysetPosition

// Decode (returns OffsetPosition|KeysetPosition).
$position = $codec->decode($token);
```

### Module Bindings

`module.php` registers the following default bindings:

| Interface | Default Implementation |
|---|---|
| `PaginationStrategyInterface` | `KeysetPaginationStrategy` |
| `RowCounterInterface` | `ExactRowCounter` |

Override either binding via a Marko Preference when a different strategy or counter is needed application-wide, or pass concrete instances directly when configuring a specific repository.

### Exceptions

| Class | Thrown when |
|---|---|
| `EmptySortException` | `Sort` constructed with no fields |
| `InvalidPageSizeException` | `PageRequest` size is `<= 0` |
| `InvalidPositionTokenException` | Token is malformed or uses an unsupported version |
| `IncompatiblePositionException` | Token type does not match the strategy |
| `MissingCursorValueExtractorException` | Keyset strategy needs an extractor but none was supplied |
| `PageOutOfRangeException` | `positionForPage()` called with a page outside `[1, totalPages]` |

## Related Packages

- [markommerce/catalog](/docs/packages/catalog/) --- Uses `criteria` to paginate category product listings; provides `PaginationOptionsResolver` and `CatalogPaginationConfig` to translate HTTP request parameters into `PageRequest` instances
- [markommerce/catalog-storefront](/docs/packages/catalog-storefront/) --- Storefront route and Latte templates for the paginated product grid
