# Task 018: `storefront` profile + injectable `ProjectPaths` base path

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
The harness plumbing needed before a request→HTML helper can exist (task 019): (1) a `StoreProfile::storefront()` preset that boots the full storefront rendering stack, and (2) a controllable `ProjectPaths` base path on the booted container so each profile/worker gets an isolated dir for compiled layout artifacts and Vite assets (today it's hardcoded). Split out from the original task 018 so the helper (019) builds on stable plumbing.

## Context
### Storefront profile
- Add `StoreProfile::storefront(string $vendorDir)` = `of($vendorDir, 'markommerce/catalog-storefront', 'markommerce/theme-blank', 'marko/database-pgsql')`.
- Confirm the transitive set (via `ModuleResolver::resolveFrom()`) resolves everything needed for real rendering: `marko/{routing, view, view-latte, vite}` + `markommerce/{catalog, catalog-storefront, catalog-price-index, config, config-pgsql, layout, frontend, theme-blank}`, and EXCLUDES `scope`/`locale`/`market`/`catalog-scope`/`catalog-storefront-scope` (a non-scoped storefront). If `resolveFrom()` does NOT transitively pull the marko framework rendering packages (routing/view-latte/vite) through `installed.json` require chains, add them as explicit root packages in the preset so they're present.
- A scoped storefront profile (market/locale) is future work — note it; the SEO/PresentationSwitch tests (task 021) drive per-test behavior via config writes, not scope.

### Injectable `ProjectPaths` base (harness change)
- `ContainerBootstrapper::build()` currently HARDCODES `ProjectPaths` to `sys_get_temp_dir() . '/markommerce-bootstrapper-' . getmypid()` with no hook. Make the base path controllable: add an optional base-path (or `ProjectPaths`) parameter to `ContainerBootstrapper::build()` / `bootedContainer()` and thread it through `StoreProfile::boot()` (e.g. `boot(ConnectionInterface $conn, ?string $projectBasePath = null)`), defaulting to the current behavior when omitted.
- The base dir must be **unique per worker** (include `getmypid()` + a random/worker-token suffix) so parallel workers don't collide on compiled artifacts (`var/cache/markommerce/layouts.php`) or the Vite manifest path (`public/build/...`). Task 019's `handle()` and the layout/CompileIfStale middleware + `Vite` all read paths from this `ProjectPaths`.
- Keep it backward compatible: existing harness tests (007/009/010/011/013/017) that call `boot()`/`build()` without the new arg must still pass unchanged.

## Requirements (Test Descriptions)
- [x] `it resolves the storefront module set including marko routing view-latte and vite`
- [x] `it excludes scope locale and market modules from the storefront profile`
- [x] `it boots the storefront profile against a connection and resolves a storefront service` (group integration-destructive)
- [x] `it binds ProjectPaths to a caller-provided base path`
- [x] `it defaults ProjectPaths to a per-process temp base when no base path is given`
- [x] `it keeps existing profiles booting unchanged when no base path is provided` (regression)

## Acceptance Criteria
- `StoreProfile::storefront()` resolves the full non-scoped storefront rendering stack.
- `ContainerBootstrapper`/`StoreProfile::boot()` accept an optional, per-worker-unique base path that drives the container's `ProjectPaths`; default behavior unchanged.
- Existing harness/integration tests still green; PHPStan level 8 clean (`php -d memory_limit=2G`).

## Implementation Notes

- `StoreProfile::storefront()` = `of($vendorDir, 'markommerce/catalog-storefront', 'markommerce/theme-blank', 'marko/database-pgsql', 'markommerce/config-pgsql')`. The `marko/routing`, `view`, `view-latte`, `vite` packages are pulled in transitively by `catalog-storefront`/`theme-blank`. The `markommerce/config-pgsql` was added as an explicit root since it's not transitively required.
- Base `markommerce/scope` and `markommerce/locale` ARE in the transitive closure (pulled via `money-intl` → `locale`/`scope`); the "excludes" test checks that the scoped extension packages (`catalog-scope`, `catalog-storefront-scope`, `catalog-market`, `catalog-locale`, `catalog-price-index-market`, `market`) are absent.
- `ContainerBootstrapper::build()` and `bootedContainer()` gained an optional `?string $projectBasePath = null` 4th parameter. `StoreProfile::boot()` got a matching `?string $projectBasePath = null` 2nd parameter, forwarded to `bootedContainer()`. All existing call sites pass nothing and get the old default behavior unchanged.
