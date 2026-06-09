# Task 007: `StoreProfile` builder + `BootedStore` + scope config

**Status**: completed
**Depends on**: 004, 006
**Retry count**: 0

## Description
Build the developer-facing `StoreProfile` abstraction: a fluent, named builder that selects root packages, injects scope (market/locale) values, and `boot()`s into a `BootedStore` (configured container + connection + active-scope helper). Provides the three named framework presets and `fromInstalled()`. This is the centerpiece API.

## Context
- Live in `packages/testing/src/Profile/` (`StoreProfile.php`, `BootedStore.php`).
- Builder surface:
  - `StoreProfile::of(string ...$rootPackages): self` — uses `ModuleResolver::resolveFrom()` (task 005) to get the transitive module set.
  - `StoreProfile::fromInstalled(): self` — uses `ModuleResolver::resolveAllInstalled()`; scope VALUES come from the app's REAL config (see below).
  - `->withMarkets(string ...$markets): self`, `->withLocale(string $market, string $locale): self` (and/or `->withLocales(...)`) — inject scope axis values for explicit profiles.
  - Named presets:
    - `simple()` = `of('markommerce/catalog')` (+ whatever it pulls: currency, config, scope, money…); no market/locale axis values beyond defaults.
    - `singleMarketTwoLocales()` = catalog + `markommerce/locale` (+ config-scope); locale axis values [en, de]; single market.
    - `twoMarketsTwoLocales()` = + `markommerce/market`, `markommerce/catalog-market`, `markommerce/catalog-price-index-market`; market axis [us, eu]; a locale per market.
- **Scope config injection** (VERIFIED mechanism): scope axes are read by `PhpScopeRegistry` from a `ConfigRepository` under `scope.axes`. Each package's `config/scope.php` declares its axis with only a `default`. To add values, the profile must MERGE the merged package configs with the explicit axis values into `scope.axes.<axis>.scopes` before constructing the `ConfigRepository` passed to the `ContainerBootstrapper`. Use `ConfigDiscovery::discover(modulePaths, rootConfigPath)` over the resolved module paths to gather base config, then deep-merge the builder's market/locale values.
- **`fromInstalled()` scope**: do NOT inject explicit values — call `ConfigDiscovery::discover(array $modulePaths, string $rootConfigPath)` (CONFIRMED signature, `marko/packages/config/src/ConfigDiscovery.php:19-22`) over the resolved installed module paths + the app's ROOT config dir, so the merchant's real `scope.axes` (their configured markets/locales) populate naturally. **DECISION (do not re-investigate auto-location)**: auto-locating a consuming app's root config dir from a `require-dev` package context is NOT reliable (no canonical anchor). Make the explicit form the primary API: `fromInstalled(string $appConfigPath)` — REQUIRE the caller pass the app's config dir (or app root from which `config/` is derived). Optionally also accept an env var (e.g. `MARKO_APP_CONFIG_PATH`) as a convenience fallback. Document clearly. Do NOT silently default to a guessed path. If `$appConfigPath` is omitted AND no env var is set, throw a clear error (with suggestion) rather than discovering an empty/wrong config.
- `boot(): BootedStore` — builds config → `ContainerBootstrapper` → booted container bound to a connection (the connection/DB is supplied by the lifecycle in task 008; for this task, accept a `ConnectionInterface` so it's testable in isolation).
- `BootedStore`:
  - `get(string $id)` / expose the container for resolving services/repositories.
  - `inScope(?string $market = null, ?string $locale = null, callable $fn)` — set active scope via `ScopeContext::in('market', …)` / `in('locale', …)`, run `$fn`, clear after (mirror Tier2's `$scopeContext->in(...)` / `clearAll()`). **GOTCHA (CONFIRMED)**: `ScopeContext::in($axis, $path)` VALIDATES the axis against the registry and THROWS if the axis is not declared (`ScopeContext.php:30-34`, `!$registry->hasAxis($axis)`). So only call `in()` for axes that are non-null AND present in the profile — calling `inScope(market: 'eu')` on the `simple()` profile (no market axis) would throw. Skip null args; consider throwing a clear, testing-package error (with suggestion) if a caller passes an axis the profile lacks, rather than letting marko's lower-level error surface.
  - expose the resolved entity dirs / module set (so task 008 can build the matching schema).

## Requirements (Test Descriptions)
- [x] `it resolves the module set for the simple profile`
- [x] `it resolves market and locale modules for the two-markets profile`
- [x] `it injects market axis values into the scope config` (resolved ScopeRegistry reports the markets; group integration-destructive)
- [x] `it injects locale axis values for a single-market-two-locale profile` (group integration-destructive)
- [x] `it boots a container whose repositories use the supplied connection` (group integration-destructive)
- [x] `it runs a closure within an active market and locale scope` (group integration-destructive)
- [x] `it throws when inScope is given an axis the profile does not declare`
- [x] `it requires an explicit app config path for fromInstalled`
- [x] `it exposes the entity directories for the booted module set`

## Acceptance Criteria
- All three named presets resolve the intended module sets and scope values.
- `fromInstalled()` boots the installed module set with app config (scope values from real config).
- `inScope()` activates and clears market/locale scope.
- `BootedStore` exposes services + the entity-dir set for schema provisioning.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

### Files created
- `packages/testing/src/Profile/StoreProfile.php` — fluent builder with `of()`, `fromInstalled()`, `simple()`, `singleMarketTwoLocales()`, `twoMarketsTwoLocales()`, `withMarkets()`, `withLocale()`, `withLocales()`, `boot()`, `entityDirs()`.
- `packages/testing/src/Profile/BootedStore.php` — booted container wrapper with `get()`, `inScope()`, `entityDirs()`, `container()`.
- `packages/testing/src/Profile/Exceptions/MissingAppConfigPathException.php`
- `packages/testing/src/Profile/Exceptions/UndeclaredAxisException.php`
- `packages/testing/tests/Unit/Profile/StoreProfileTest.php`
- `packages/testing/tests/Feature/Profile/BootedStoreTest.php`

### Key decisions
- `simple()` includes `marko/database-pgsql` in the root packages so the pgsql query builder factory is bound and repositories can be resolved.
- `ContainerBootstrapper::PREFERENCE_SKIP_LIST` extended with `AscendingIndexedPriceSortOrder` and `DescendingIndexedPriceSortOrder` — the `catalog-price-index-market` Preferences replace these with scoped variants that do NOT extend the originals, causing a TypeError in the `catalog-price-index` boot closure if the Preference is applied.
- `twoMarketsTwoLocales()` includes `catalog-price-index-market` as a root as specified; the PREFERENCE_SKIP_LIST fix makes this work correctly.
- Config is built from `ConfigDiscovery::discover(modulePaths, rootConfigPath='')` for explicit profiles (empty root dir is safe since `ConfigDiscovery` skips missing dirs), then deep-merged with explicit axis values.
- `singleMarketTwoLocales` adds locales under the `'default'` market key (no dedicated market axis values needed for single-market scenario).
