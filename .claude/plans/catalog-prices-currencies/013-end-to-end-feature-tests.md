# Task 013: end-to-end feature tests (Tier-1 + Tier-3)

**Status**: complete
**Depends on**: 012, 005, 007, 008
**Retry count**: 0

## Description
Add cross-package feature tests proving the two headline scenarios work end to end: a single-market shop (global currency/tax, no market packages exercised) and an international shop (per-market price, currency, and tax-mode overrides). Confirms the layering and fallbacks hold together. Note: `PriceResolverInterface::resolve()` returns only `Money`; the tax mode is resolved **independently** via `TaxModeResolver->mode()`, not carried on the price result.

## Context
- Place under the monorepo-level `tests/` (Feature) or the most appropriate package `tests/Feature/`; follow existing feature-test setup.
- **Tier-1 scenario:** set `currency/base` (e.g. `EUR`), store a `Product.priceAmount`, resolve via `PriceResolverInterface`, and format via `MoneyFormatter` for a locale — assert the rendered string and that no market scope is required. Separately assert `TaxModeResolver->mode()` returns the global default (`Exclusive`).
- **Tier-3 scenario:** with `catalog-market` + `currency-market` + `tax-market` active, set a `market` scope, configure a per-market currency override, a per-market price override, and a per-market tax-mode override. Assert (a) the market-specific `Money` from `PriceResolverInterface::resolve()`, (b) the market-specific `TaxMode` from `TaxModeResolver->mode()` (resolved independently, both honoring the active market scope), and (c) the formatted output; then assert fallback to globals outside the market scope.
- **Concrete setup requirements (verified against the codebase):**
  - **Register a non-default market scope.** `packages/market/config/scope.php` ships only `market => {default}`. Add a real market path (e.g. `us`) to `scope.axes.market.scopes` in the test config, or `ScopeContext::in('market', 'us')` throws `ScopeContextException`. (Mirror how `catalog-scope` Tier2 adds `de`/`fr` to the locale axis.)
  - **Config discovery.** `CurrencyConfig` and `TaxConfig` must be discoverable by `ConfigClassDiscovery` (their modules present in the test `ModuleRepository`) so `ConfigRegistry::definition()` resolves and the scoped resolver finds the registered axes.
  - **Per-market price override lives in a companion.** Attach a `ProductScopedOverrides` to the `Product` and set `setOverride('market:us', 'priceAmount', '<decimal>')`; resolution reads it via `ScopeResolver::resolved($product, 'priceAmount')` with the `us` market active. A bare `Product` only yields the global amount.
  - **Per-market config overrides** (currency/base, tax flag) are set via `ScopedConfigWriter`/`InMemoryScopedConfigStorage` keyed to the `us` market.
  - **Boot order:** run all bridge boots (which call `ScopedFieldRegistry->register(...)`) BEFORE the first resolution — `ScopeMetadataFactory` freezes per-class metadata on first `for()`.
- Reset `ScopeContext` (call `clearAll()`) between scenarios — it is a mutable singleton with no auto-reset. Also assert the resolver itself restores the prior `market` after a `resolve()` (task 012's snapshot/restore), which is what the "does not leak scope state" test verifies.
- Exercises tasks 002, 004, 005, 006, 007, 008, 010, 012 together.

## Requirements (Test Descriptions)
- [x] `it resolves and formats a product price for a single market shop using global currency`
- [x] `it formats the price for the active locale in a single market shop`
- [x] `it resolves the global tax mode for a single market shop`
- [x] `it resolves a per market price and currency for an international shop`
- [x] `it resolves a per market tax mode independently of the price for an international shop`
- [x] `it falls back to global price currency and tax mode outside any market scope`
- [x] `it does not leak scope state between resolutions`

## Acceptance Criteria
- Both tiers verified end to end across package boundaries.
- All requirements have passing tests.
- `composer test` green; coverage ≥ 80% overall.
- Follows standards.

## Implementation Notes
- Tests placed in `tests/Feature/PricingEndToEndTest.php` (monorepo root level) to access all packages (money-intl, currency-market, tax-market) that pricing's own composer.json does not require.
- Tier-1 container uses no scope axes (empty `scope.axes`); Tier-3 container includes `market` axis with `default` and `us` scopes.
- `ScopedConfigResolver` is used for Tier-3 tests, with `InMemoryScopedConfigStorage` populated directly via `saveOverride()` (mirrors pattern from currency-market and tax-market feature tests).
- `MoneyFormatter::formatFor()` asserts presence of `€`, `49`, and `99` rather than a locale-specific decimal separator because the Docker container's ICU library does not distinguish `de_DE` from `en` formatting.
- `DefaultScopeGuard::reset()` called at the start of each test; `ScopeContext::clearAll()` called between scenarios to prevent cross-test leakage.
