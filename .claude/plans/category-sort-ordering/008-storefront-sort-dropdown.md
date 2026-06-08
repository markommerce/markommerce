# Task 008: Storefront sort dropdown from the registry (catalog-storefront)

**Status**: completed
**Depends on**: 003, 004, 005
**Retry count**: 0

## Description
Surface the available sort orders on the category page as a dropdown sourced from `CategorySortOrderRegistry->all()`, with the active selection reflected and preserved across pagination. With only `catalog-price-index` installed the dropdown shows Position + Price asc/desc; with neither, just Position.

## Context
- Related files:
  - `packages/catalog-storefront/src/Component/ProductGridComponent.php` — inject `CategorySortOrderRegistry`; expose the available orders (key + label) and the active key on `ProductGridData`. **MANDATORY shape-change fix (task 004 removes `ResolvedPaginationOptions::pageRequest`):** line 113 reads `$options->pageRequest->size` — change to `$options->size`. The active sort key for the selected `<option>` is `$options->sortOrder->key()` (NOT the request `$sort` string, so the default fallback is reflected correctly). Pagination URL builders already preserve the request `sort` param via `http_build_query` — but they preserve the RAW request string; ensure the dropdown's `activeSort` uses `$options->sortOrder->key()`.
  - `packages/catalog-storefront/src/Controller/CategoryController.php` — **MANDATORY shape-change fix:** lines 102-103 read `$defaultOptions?->pageRequest->size` / `$resolvedOptions->pageRequest->size` → change to `->size`. Lines 108-109 read `$options->pageRequest->sort->fields[0]->column` to decide whether to emit a `?sort=` canonical param → change to compare `$resolvedOptions->sortOrder->key()` against `$defaultOptions?->sortOrder->key()` (comparing keys is more correct than comparing raw SQL columns). This file is otherwise untracked by any task — it MUST be edited here or the storefront won't compile.
  - Test fakes building `ResolvedPaginationOptions` with `size: $options->pageRequest->size`: `catalog-storefront/tests/Feature/{CategoryControllerTest,Tier1EndToEndTest,CategoryPageFragmentTest,CategoryLayoutTest,CategorySeoTest}.php`. Update each to construct/read via `sortOrder:` + `size:`. (`catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php:225` lives in a different package — see "Cross-package note" below.)
  - `packages/catalog-storefront/src/Data/ProductGridData.php` — add `sortOptions` (list of key+label) and `activeSort` (string), both with safe defaults so the readonly constructor stays backward compatible for existing test constructions.
  - `packages/catalog-storefront/resources/views/components/product-grid.latte` — render a `<form>`/`<select>` (GET) that submits `?sort=` and resets to page 1; no-JS friendly. Keep it crawlable/simple, consistent with existing markup (`mk-*` elements).
  - `packages/catalog-storefront/src/Layout/CategoryProductGridLayout.php` already sources `?sort=` via `Source::query('sort', '', 'string')`.
- The active key is the resolved order's `key()` (from the resolver), so the selected `<option>` matches what was actually applied (including the default fallback).
- **Cross-package note:** `catalog-storefront-scope` has a test (`ScopedProductGridComponentTest.php:225`) that constructs `ResolvedPaginationOptions` with `size: $options->pageRequest->size`. This package is NOT otherwise in scope; updating it here keeps the suite green. Add a one-line fix to that test fake (`sortOrder:` + `size:`) as part of this task, or flag it for the user if the package shouldn't be touched. It is a compile-time break, so it must be addressed before 009.

## Requirements (Test Descriptions)
- [x] `it exposes the registered sort orders as dropdown options`
- [x] `it marks the active sort order as selected`
- [x] `it defaults the active sort to position when no sort is requested`
- [x] `it includes the price orders when the price index package is registered`
- [x] `it renders a sort select that submits the sort query parameter`
- [x] `it resets to the first page when the sort changes`

## Acceptance Criteria
- Dropdown options come from the registry (not a hardcoded list), so installing a package that registers an order makes it appear automatically.
- Changing sort navigates to page 1 with `?sort=<key>`; selection persists across page links.
- All `$options->pageRequest->size` / `$options->pageRequest->sort->...` reads in `ProductGridComponent` and `CategoryController` are migrated to `$options->size` / `$options->sortOrder->key()`; the canonical-URL `?sort=` normalization compares sort-order KEYS.
- All affected storefront (and the one `catalog-storefront-scope`) test fakes construct `ResolvedPaginationOptions` via `sortOrder:` + `size:`.
- PHPStan level 8 clean; existing storefront component/template/controller tests still pass.

## Implementation Notes

- Fixed `PaginationOptionsResolver` constructor calls in all affected test helpers to pass `CategorySortOrderRegistry`; test registries include `position` (and `name` where tests use sort=name)
- Fixed config key `allowedSorts` → `enabledSorts` in all test config resolvers (the config class uses `enabledSorts`)
- Fixed `$options->pageRequest->size` → `$options->size` in `ProductGridComponent`, `CategoryController`, and all test fakes
- Fixed `$options->pageRequest->sort->fields[0]->column` comparisons in `CategoryController::buildCanonicalUrl` → compare sort-order keys via `$options->sortOrder->key()`
- Added `sortOptions` (list of {key, label}) and `activeSort` (string) with safe defaults to `ProductGridData`
- Injected `CategorySortOrderRegistry` as last optional parameter (default: empty registry) in `ProductGridComponent`
- Sort dropdown rendered in `product-grid.latte` as a GET form with `<select name="sort">`; only shown when 2+ options; no hidden `page` input so navigating resets to page 1
- `ScopedProductGridComponent::data()` updated to forward `sortOptions` and `activeSort` from parent data
- Cross-package fix: `catalog-storefront-scope` test fake updated from `$options->pageRequest->size` to `$options->size`
