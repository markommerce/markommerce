# Task 007: tax-market bridge

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Create the `markommerce/tax-market` bridge that registers the `tax/prices_include_tax` config key on the `market` axis, allowing per-market tax-mode overrides while `tax` stays market-agnostic.

## Context
- New package `packages/tax-market/` — minimal, mostly `module.php`. PSR-4 `Markommerce\TaxMarket\`. Register in root `composer.json` (require + `autoload-dev`).
- `require`: `markommerce/tax`, `markommerce/config-scope`, `markommerce/market` (self.version).
- `module.php` `boot` closure calls `ScopedFieldRegistry->register(entityClass: TaxConfig::class, property: 'pricesIncludeTax', axes: ['market'])`. Mirror `currency-market` (task 005).
- **Same verified mechanism as task 005:** the imperative `register()` is honored by `ScopedConfigResolver::resolvedAt()` because it reads axes from `ScopedFieldRegistry`, not from `#[Scoped]` attributes; `TaxConfig` stays market-agnostic. `$definition->field` equals the property name (`'pricesIncludeTax'`).
- The bridge `require`s `markommerce/market` + `markommerce/config-scope`, so the `market` axis exists before boot (else `register()` throws `UnknownAxisException`).
- **Test-setup gotchas (same as task 005):** `TaxConfig` must be discoverable by `ConfigClassDiscovery` (the `tax` module in the test `ModuleRepository`); add a concrete market scope to `scope.axes.market.scopes` so `ScopeContext::in('market', ...)` succeeds; set the per-market override via `ScopedConfigWriter`/`InMemoryScopedConfigStorage`. Note `pricesIncludeTax` is a `bool` — `ScopedDataSerializer` and `ValueCaster` round-trip booleans, but verify the override casts back to `bool` (it drives the `TaxMode` enum mapping).
- **Author `packages/tax-market/README.md`** per `.claude/package-standard.md` (mirror `packages/market/README.md`): one-line purpose (registers the tax-mode key on the `market` axis), install, brief example, docs link.

## Requirements (Test Descriptions)
- [x] `it registers the tax mode config key on the market axis`
- [x] `it resolves a per market tax mode override when one is set`
- [x] `it falls back to the global tax mode when no market override exists`

## Acceptance Criteria
- Package registered in root `composer.json`.
- `axesForProperty(TaxConfig::class, 'pricesIncludeTax')` returns `['market']` after boot.
- `packages/tax-market/README.md` exists and follows the package README standard.
- All requirements have passing tests; coverage ≥ 80%.
- Follows standards.

## Implementation Notes
- `packages/tax-market/module.php` created: `boot` closure calls `ScopedFieldRegistry->register(entityClass: TaxConfig::class, property: 'pricesIncludeTax', axes: ['market'])`.
- Tests live in `packages/tax-market/tests/Feature/BootContributionTest.php` and use the same pattern as `catalog-locale/tests/Feature/BootContributionTest.php`: build a container wired with scope module bindings, DependencyResolver orders manifests, boot closures run, then assertions against `ScopedFieldRegistry`.
- Test config includes `'us'` scope under `scope.axes.market.scopes` so `ScopeContext::in('market', 'us')` succeeds.
- Override resolution tests use `OverrideMatcher` resolved from the container (takes `SignatureCandidateEnumerator`, not `ScopeRegistryInterface`) and `InMemoryScopedConfigStorage` for the per-market override value.
- `README.md`, `LICENSE`, `.gitattributes`, `tests/Pest.php` created per package standard.
- All 3 requirements have passing tests.
