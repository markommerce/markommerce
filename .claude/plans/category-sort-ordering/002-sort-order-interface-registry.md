# Task 002: CategorySortOrderInterface + CategorySortOrderRegistry (catalog)

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Define the sort-order contract and registry in `catalog`. A sort order owns everything its `ORDER BY` needs: a stable key, a display label, whether it works with keyset pagination, any JOINs to add to the category product query, and the `SortField`s to apply. The registry lets any package register additional orders, mirroring `PriceContributorRegistry`.

## Context
- Related files:
  - New: `packages/catalog/src/Sorting/CategorySortOrderInterface.php`
  - New: `packages/catalog/src/Sorting/CategorySortOrderRegistry.php`
  - Pattern to copy: `packages/catalog/src/Pricing/PriceContributorRegistry.php` (priority-ordered `register()`/`all()`)
  - `packages/criteria/src/Sort/SortField.php` (return type of `sortFields()`)
  - `marko/packages/database/src/Repository/RepositoryQueryBuilder.php` (the type `prepareQuery()` receives; has `leftJoin`)
- Interface methods:
  - `key(): string` — URL/config token (e.g. `price_asc`)
  - `label(): string` — storefront dropdown label
  - `supportsKeyset(): bool`
  - `prepareQuery(RepositoryQueryBuilder $query): void` — add any JOINs (no-op for column-only orders)
  - `sortFields(): array` — `list<SortField>`

## Requirements (Test Descriptions)
- [x] `it registers a sort order and exposes it via all`
- [x] `it returns registered orders sorted by ascending priority`
- [x] `it gets a registered order by its key`
- [x] `it returns null when getting an unknown key`
- [x] `it reports whether a key is registered`
- [x] `it keeps the first registered order when two share the same key` (or documents last-wins — pick one and test it)

## Acceptance Criteria
- `CategorySortOrderInterface` lives in `Markommerce\Catalog\Sorting`, no `final`, `@throws` tags where relevant.
- `CategorySortOrderRegistry` offers `register(CategorySortOrderInterface $order, int $priority = 0)`, `all(): list<CategorySortOrderInterface>` (priority-ordered), `get(string $key): ?CategorySortOrderInterface`, `has(string $key): bool`.
- No DB or storefront dependencies introduced into the contract beyond the marko query-builder type already used by the criteria layer.
- PHPStan level 8 clean.

## Implementation Notes
- `CategorySortOrderInterface` lives in `Markommerce\Catalog\Sorting` with methods: `key()`, `label()`, `supportsKeyset()`, `prepareQuery(RepositoryQueryBuilder)`, `sortFields(): list<SortField>`.
- `CategorySortOrderRegistry` uses PHP 8.5 `array_find`/`array_any` for `get()`/`has()`. First-registered wins on duplicate keys (silently ignored on re-registration).
- `catalog-storefront` `RelocationTest` allowed-dirs list updated to include `Sorting`.
- PHPStan level 8 clean.
