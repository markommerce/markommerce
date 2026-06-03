# Task 010: catalog-market — register Product.priceAmount on market axis

**Status**: pending
**Depends on**: 009
**Retry count**: 0

## Description
Activate the existing `catalog-market` placeholder `boot` closure to register `Product.priceAmount` on the `market` axis, enabling per-market base prices that fall back to the global product price. Only the decimal amount scalar is scoped — it round-trips cleanly through the JSON scoped-overrides storage.

## Context
- File: `packages/catalog-market/module.php` — replace the placeholder comment in the `boot` closure with a real registration:
  ```php
  $scopedFieldRegistry->register(
      entityClass: Product::class,
      property: 'priceAmount',
      axes: ['market'],
  );
  ```
  Follow the exact pattern in `packages/catalog-locale/module.php`.
- No new package; this edits the existing bridge. The `catalog-scope` machinery (`ProductScopedOverrides` companion + `ScopedDataSerializer`) already persists/reads per-scope scalar values — a decimal **string** round-trips cleanly (`ScopedDataSerializer` passes scalars through untouched). No storage changes needed.
- The existing `boot` closure already injects `ScopedFieldRegistry` and `catalog-market` already `require`s `markommerce/market`, so the `market` axis is registered before this boot runs (DependencyResolver ordering). Just replace the placeholder body.
- **Read mechanism (for the test):** the per-market value is read via `Markommerce\Scope\Resolver\ScopeResolver::resolved($product, 'priceAmount')` against the active `ScopeContext`, where the override lives in an attached `ProductScopedOverrides` companion (set via `$overrides->setOverride('market:<path>', 'priceAmount', '<decimal>')`). Mirror the resolution assertions in `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php` (swap `locale`→`market`, `name`→`priceAmount`).
- **Test-setup gotcha — register a non-default market scope:** `packages/market/config/scope.php` ships ONLY `market => {default}`. To exercise a real override the test MUST add a concrete market path (e.g. `us`) to the `scope.axes.market.scopes` config before booting, because `ScopeContext::in('market', 'us')` throws `ScopeContextException` for an unregistered path. Follow the Tier2 test which adds `de`/`fr` to the locale axis.
- **Ordering gotcha — `ScopeMetadataFactory` freezes per-class metadata on first `for()`:** the registration of `priceAmount` MUST happen (bridge boot) BEFORE the first `ScopeResolver::resolved($product, ...)`/`ScopeMetadataFactory::for(Product::class)` call, or the cached metadata won't include `priceAmount`. In the real app this is guaranteed by boot order; in tests, run the bridge boot before resolving.
- Update the stale module-doc comment that says no scoped fields are registered.
- **PRE-EXISTING FAILING README TESTS — fix as part of this task.** `packages/catalog-market/tests/ReadmeTest.php` currently FAILS 2 tests (run it Docker-wrapped to see): they assert the README contains `## Placeholder Status`, `empty boot closure`, `price`, `visibility`, and `FEATURES.md`. The README was already updated away from placeholder language, and THIS task makes `catalog-market` genuinely non-placeholder by registering `Product.priceAmount`. Therefore:
  - Update `packages/catalog-market/README.md` to document the now-active market-scoped `Product.priceAmount` registration (remove any "planned/placeholder" framing for price; describe the real boot behavior). Keep it standard-compliant per `.claude/package-standard.md`.
  - Update `packages/catalog-market/tests/ReadmeTest.php` so its assertions match the new, accurate README (drop the `## Placeholder Status` / `empty boot closure` / `FEATURES.md`-placeholder expectations; assert instead that the README documents the market-scoped `priceAmount` field). Do NOT weaken coverage — keep meaningful assertions about the README content.
  - After this task, `docker compose ... app ./vendor/bin/pest packages/catalog-market/tests/` must be FULLY green (0 failures).

## Requirements (Test Descriptions)
- [x] `it registers the product price amount on the market axis`
- [x] `it resolves a per market product price override when one is set`
- [x] `it falls back to the global product price when no market override exists`

## Acceptance Criteria
- `axesForProperty(Product::class, 'priceAmount')` returns `['market']` after boot.
- Per-market override and fallback both verified by tests.
- `packages/catalog-market/tests/ReadmeTest.php` passes (README updated to reflect the active price registration; no pre-existing failures remain).
- The full `packages/catalog-market/tests/` suite is green.
- All requirements have passing tests; coverage ≥ 80%.
- Follows standards; stale comment removed.

## Implementation Notes
- Replaced placeholder `boot` closure in `packages/catalog-market/module.php` with real `ScopedFieldRegistry->register(Product::class, 'priceAmount', ['market'])` call.
- Updated module doc comment to reflect active registration (removed placeholder/FEATURES.md references).
- Added `packages/catalog-market/tests/Feature/PriceAmountScopeResolutionTest.php` with a full container boot (scope+market+catalog+catalog-scope+catalog-market modules) and in-memory scope context to exercise the per-market override and fallback resolution paths. The test config adds `us` as a concrete market scope alongside `default`.
- Updated `packages/catalog-market/tests/Unit/BootClosureTest.php`: replaced the no-op test with `registers the product price amount on the market axis` and a separate boot-signature test; extracted fake scope registry into a helper function to avoid anonymous class duplication.
- Updated `packages/catalog-market/tests/PackageScaffoldingTest.php`: renamed `registers no scoped fields` → `registers Product.priceAmount on the market axis when the boot closure runs` and flipped assertion to `toBeTrue()` + `axesForProperty` check; renamed `ships an empty-but-callable boot closure` to `ships a callable boot closure`.
- Fixed 2 pre-existing `ReadmeTest.php` failures: rewrote `README.md` to document the active priceAmount registration (removed `## Placeholder Status` / `empty boot closure` / `FEATURES.md` framing); updated `ReadmeTest.php` assertions to verify `priceAmount`, `market`, `ScopeResolver`, `ProductScopedOverrides`, and `setOverride` are documented.
