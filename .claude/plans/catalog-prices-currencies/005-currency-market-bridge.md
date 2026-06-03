# Task 005: currency-market bridge

**Status**: pending
**Depends on**: 004
**Retry count**: 0

## Description
Create the `markommerce/currency-market` bridge whose only job is to register the `currency/base` config key on the `market` axis, so markets can override the base currency while the core `currency` package remains market-agnostic.

## Context
- New package `packages/currency-market/` — minimal, mostly `module.php`. PSR-4 `Markommerce\CurrencyMarket\`. Register in root `composer.json` (require + `autoload-dev`).
- `require`: `markommerce/currency`, `markommerce/config-scope`, `markommerce/market` (all self.version).
- `module.php` `boot` closure receives `ScopedFieldRegistry` and calls `register(entityClass: CurrencyConfig::class, property: 'base', axes: ['market'])`. Mirror the imperative registration pattern in `packages/catalog-locale/module.php`.
- **Verified that this works (the key risk in this plan):** `ScopedConfigResolver::resolvedAt()` (packages/config-scope) reads axes purely from `ScopedFieldRegistry->axesForProperty($definition->configClass, $definition->field)` at resolve time — it does NOT re-inspect `#[Scoped]` attributes. config-scope's own boot only auto-registers properties that DO carry `#[Scoped]`, but the registry is additive, so an imperative `register()` from this bridge is honored identically. The core `CurrencyConfig` therefore stays market-agnostic (no `#[Scoped]`), exactly as the plan intends. `$definition->field` equals the property name (`'base'`), confirmed against `ConfigRegistry`/`ConfigDefinition`.
- The bridge `require`s `markommerce/market` (axis) and `markommerce/config-scope`, so the `market` axis is registered before this boot runs (`register()` throws `UnknownAxisException` otherwise). DependencyResolver guarantees ordering.
- After this bridge loads, the Preference-bound `ScopedConfigResolver` applies per-market overrides for `currency/base`, falling back to the global default when no market override exists. `CurrencyResolver` (task 004) reads via `ConfigResolver::resolved(CurrencyConfig::class, 'base')`, so no code change is needed there.
- **Test-setup gotchas:**
  - For the override/fallback tests, `CurrencyConfig` must be discoverable by `ConfigClassDiscovery` (it lives in `packages/currency/src/Config/` and the `currency` module must be in the test `ModuleRepository`) so `ConfigRegistry::definition()` resolves it. Model the harness on `packages/config-scope/tests/Feature/BootContributionTest.php`.
  - `ScopeContext::in('market', '<path>')` requires a registered market path: add a concrete market scope (e.g. `us`) to `scope.axes.market.scopes` in the test config, since `packages/market/config/scope.php` ships only `default`.
  - Set the per-market override via `ScopedConfigWriter`/`InMemoryScopedConfigStorage` (config-scope), then resolve with the market active.
- **Author `packages/currency-market/README.md`** per `.claude/package-standard.md` (mirror `packages/market/README.md`): one-line purpose (registers `currency/base` on the `market` axis), install, brief example, docs link.

## Requirements (Test Descriptions)
- [x] `it registers the currency base config key on the market axis`
- [x] `it resolves a per market base currency override when one is set`
- [x] `it falls back to the global base currency when no market override exists`

## Acceptance Criteria
- Package registered in root `composer.json`.
- `ScopedFieldRegistry->axesForProperty(CurrencyConfig::class, 'base')` returns `['market']` after boot.
- `packages/currency-market/README.md` exists and follows the package README standard.
- All requirements have passing tests; coverage ≥ 80%.
- Follows standards.

## Implementation Notes
- Mirrored `packages/tax-market/` exactly, swapping `TaxConfig`/`pricesIncludeTax` → `CurrencyConfig`/`base`.
- `module.php` registers `CurrencyConfig::class`, property `'base'`, axes `['market']` via `ScopedFieldRegistry->register()`.
- Test config adds `'us'` scope under `scope.axes.market.scopes` to exercise per-market override and fallback.
- PHPStan type annotation changed from `list<ModuleManifest>` to `array<ModuleManifest>` for the boot loop helper to satisfy level-8 analysis (same issue exists in tax-market).
- All files created: `module.php`, `tests/Pest.php`, `tests/Feature/BootContributionTest.php`, `README.md`, `LICENSE`, `.gitattributes`.
