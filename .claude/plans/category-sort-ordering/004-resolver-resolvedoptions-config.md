# Task 004: Resolver + ResolvedPaginationOptions + config + keyset loud error (catalog)

**Status**: completed
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Rework sort resolution so the resolver selects a registered `CategorySortOrderInterface` (instead of baking a raw token into a `SortField`/`PageRequest`), validates it against the registry, and fails loudly when an incompatible sort is requested under the keyset strategy. `ResolvedPaginationOptions` carries the selected sort order so the service can apply JOINs and build the `PageRequest` later.

## Context
- Related files:
  - `packages/catalog/src/Pagination/PaginationOptionsResolver.php` — `resolveSort()` currently validates `?sort=` against the `allowedSorts` string array and does `new SortField($resolvedSort)`; replace with registry lookup. Inject `CategorySortOrderRegistry`. **Note**: `resolveSort()` must now also receive the resolved `PaginationStrategyKind` (resolve `strategyKind` BEFORE the sort so the keyset-incompatibility check can run inside sort resolution). Remove the now-unused `new Sort(new SortField(...))` / `PageRequest::first(...)` construction at the end of `resolve()`.
  - `packages/catalog/src/Pagination/ResolvedPaginationOptions.php` — replace the baked `PageRequest` with the selected `CategorySortOrderInterface $sortOrder` plus the resolved `int $size` (keep `page`, `presentation`, `strategyKind`, `countMode`). Property order matters: existing call sites use named args, so adding `sortOrder` + `size` and removing `pageRequest` is safe for named-arg constructors but breaks positional ones — all known constructors use named args (verified).
  - `packages/catalog/src/Config/CatalogPaginationConfig.php` — keep `defaultSort` (default `'position'`). **DECIDED: RENAME `allowedSorts` → `enabledSorts`** (an exposure gate, default `[]` = "all registered orders exposed"). Rename the property, change the `#[Config(key: ...)]` from `catalog/pagination.allowedSorts` to `catalog/pagination.enabledSorts`, change the default from `['position','name','sku','price']` to `[]`, and update every reference (the resolver reads `enabledSorts`; grep the monorepo for `allowedSorts` / `pagination.allowedSorts` and update test/config fixtures). This is a deliberate, accepted BC change to a public config key.
  - `packages/catalog/src/Exceptions/InvalidPaginationConfigException.php` — extend with a keyset-incompatibility factory (loud: message/context/suggestion).
- **Downstream `ResolvedPaginationOptions` consumers that MUST be updated in this task (the shape change breaks compilation otherwise):**
  - `packages/catalog/src/Services/CategoryAssignmentService.php` — `buildPageRequest()` reads `$options->pageRequest` (lines 172, 179-180). Covered by task 005, but this task's edit to the DTO will break compilation until 005 lands → keep 005 a hard dependency of 008/009 and note the temporary breakage is expected between 004 and 005.
  - `packages/catalog-storefront/src/Component/ProductGridComponent.php:113` — `$options->pageRequest->size`. Covered by task 008 (now explicitly).
  - `packages/catalog-storefront/src/Controller/CategoryController.php:102-103,108-109` — reads `$options->pageRequest->size` and `$options->pageRequest->sort->fields[0]->column`. Covered by task 008 (now explicitly).
  - Test fakes that BUILD `ResolvedPaginationOptions` with `size: $options->pageRequest->size`: `catalog-storefront/tests/Feature/{CategoryControllerTest,Tier1EndToEndTest,CategoryPageFragmentTest,CategoryLayoutTest,CategorySeoTest}.php` and `catalog-storefront-scope/tests/Unit/Component/ScopedProductGridComponentTest.php:225`. These pass a fake `ResolvedPaginationOptions` into a stubbed service; updating the DTO breaks them. Covered by task 008.
  - `packages/catalog/tests/Unit/Pagination/PaginationOptionsResolverTest.php:60-83` — asserts on `$options->pageRequest->size`/`->sort->fields[0]->column`. Update in THIS task to assert `$options->size` and `$options->sortOrder->key()`.
  - **`PaginationOptionsResolverTest.php:105-114` (`'resolves the keyset strategy kind when configured'`) WILL BREAK.** It calls `resolve(sort: null)` with `strategy: keyset`; under the new guard the default `position` order (`supportsKeyset()=false`) now makes `resolve()` THROW the keyset-incompatibility exception instead of returning options. Fix it by registering a keyset-capable test-double order as the configured default for that one test (so it still asserts `strategyKind === Keyset`), OR split it: one test asserting keyset resolution with a keyset-capable default, and the new `'it throws a loud keyset-incompatibility exception...'` test covering the non-keyset case. Do NOT leave the old assertion as-is — it cannot pass.
- Resolution rules:
  - No `?sort=` → use `defaultSort`; if that key isn't registered, fall back to the registry default (`position` or first registered) rather than crashing.
  - `?sort=` not registered (or not in a non-empty `enabledSorts`) → throw `InvalidPaginationConfigException::forInvalidSort` listing the available keys.
  - Selected order (whether from `?sort=` OR the default) where `strategyKind === Keyset` and `!order->supportsKeyset()` → throw the new loud keyset-incompatibility exception. NOTE: because v1 ships ONLY non-keyset orders, this means `strategy=keyset` makes the category page throw on EVERY request (even with no `?sort=`). This is intended (documented in `_plan.md`), but the default config strategy is `'offset'` so the out-of-the-box path never throws. Do NOT change the default strategy.

## Requirements (Test Descriptions)
Tests that need a second registered order (beyond the catalog-native `position`) should register a test-double `CategorySortOrderInterface` into the registry — `catalog` has no price order of its own (that lives in `catalog-price-index`).

- [x] `it selects the default position order when no sort is requested`
- [x] `it falls back to position when the configured default sort is not registered`
- [x] `it selects the registered sort order matching the requested key`
- [x] `it throws an invalid sort exception listing available keys for an unregistered key`
- [x] `it excludes orders not in a non-empty enabledSorts gate`
- [x] `it throws a loud keyset-incompatibility exception when a non-keyset order is requested under the keyset strategy`
- [x] `it carries the selected sort order and resolved size on the resolved options`

## Acceptance Criteria
- `ResolvedPaginationOptions` no longer pre-bakes the sort into a `PageRequest`; it carries `CategorySortOrderInterface $sortOrder` + `int $size`.
- `strategyKind` is resolved before sort resolution so the keyset-incompatibility guard can run on the selected order.
- The keyset-incompatibility exception extends `MarkoException` with message/context/suggestion.
- `PaginationOptionsResolverTest` updated: assertions on `->pageRequest->size`/`->pageRequest->sort->fields[0]->column` become `->size` / `->sortOrder->key()`. The resolver's own unit tests register at least one extra test-double order to exercise key selection and the keyset guard.
- PHPStan level 8 clean.

## Implementation Notes
