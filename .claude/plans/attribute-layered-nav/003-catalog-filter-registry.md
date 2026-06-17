# Task 003: `catalog` filter extension point — `FilterSelection` + `ProductListFilterInterface` + registry

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a generic, attribute-agnostic product-list filter extension point to `catalog`: a `FilterSelection`
value object (the user's selected filters), a `ProductListFilterInterface` (a query contributor), and
a `ProductListFilterRegistry`. This mirrors `CategorySortOrderInterface`/registry and lets other
packages (catalog-attribute-storefront) constrain the listing query without `catalog` depending on them.

## Context
- Pattern: `packages/catalog/src/Sorting/CategorySortOrderInterface.php` + `CategorySortOrderRegistry.php`.
- Place in `packages/catalog/src/Listing/` (or `Filtering/`).
- `FilterSelection` (readonly value object): wraps `array<string, list<string>>` (filterKey → selected
  values). Methods: `forKey(string $key): list<string>` (empty if absent), `keys(): list<string>`,
  `isEmpty(): bool`, `without(string $key): self` (for disjunctive faceting — drop one key), `with(...)`
  / a `fromArray()` factory. Immutable.
- `ProductListFilterInterface`: `apply(RepositoryQueryBuilder $query, FilterSelection $selection): void`
  — a contributor that inspects the selection for the keys it handles and adds constraints (no-op when
  none apply). (No `key()` needed — a contributor may handle many dynamic keys, e.g. all attribute codes.)
- `ProductListFilterRegistry`: `register(ProductListFilterInterface $filter): void`, `all(): list<...>`.
- `catalog` defines the contract ONLY — no dependency on `catalog-attribute*`.

## Requirements (Test Descriptions)
- [x] `it returns the selected values for a key and an empty list for an absent key`
- [x] `it reports whether the selection is empty`
- [x] `it returns a copy without a given key for disjunctive faceting`
- [x] `it registers and lists product-list filter contributors`

## Acceptance Criteria
- `FilterSelection` is immutable with `forKey`/`keys`/`isEmpty`/`without`; registry holds contributors.
- No `catalog` dependency on attribute packages.

## Implementation Notes
- Created `packages/catalog/src/Filtering/FilterSelection.php` — readonly value object wrapping `array<string, list<string>>` with `forKey`, `keys`, `isEmpty`, `without`.
- Created `packages/catalog/src/Filtering/ProductListFilterInterface.php` — single `apply(RepositoryQueryBuilder, FilterSelection): void` method; no `key()` since contributors may handle many dynamic keys.
- Created `packages/catalog/src/Filtering/ProductListFilterRegistry.php` — simple append-only `register`/`all` registry (no priority, no key dedup — contributors are not keyed).
- Tests in `packages/catalog/tests/Unit/Filtering/FilterSelectionTest.php` and `ProductListFilterRegistryTest.php`.
- All 308 existing catalog tests continue to pass.
