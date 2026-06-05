# markommerce/criteria

Headless, entity-agnostic pagination engine for Markommerce --- sorting, keyset and offset strategies, position tokens, and row counting that operate directly on `marko/database` `RepositoryQueryBuilder` and `EntityCollection`.

## Installation

```bash
composer require markommerce/criteria
```

## Quick Example

```php
use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

// Build a sort and a first-page request (size 20, sorted by name ASC then created_at DESC).
$sort = new Sort(
    new SortField(column: 'name'),
    new SortField(column: 'created_at', direction: SortDirection::Descending),
);

$pageRequest = PageRequest::first(size: 20, sort: $sort);

// Paginate --- $paginationStrategy is PaginationStrategyInterface (keyset by default).
$page = $paginationStrategy->paginate($query, $pageRequest, $cursorValueExtractor);

// Iterate the current page.
foreach ($page->items as $entity) { /* ... */ }

// Move to the next page by passing the opaque position token.
if ($page->hasNext()) {
    $next = PageRequest::at(size: 20, sort: $sort, position: $page->nextPosition);
    $nextPage = $paginationStrategy->paginate($query, $next, $cursorValueExtractor);
}
```

## Documentation

Full usage, API reference, and examples: [markommerce/criteria](https://markommerce.dev/docs/packages/criteria/)
