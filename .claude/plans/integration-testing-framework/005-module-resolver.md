# Task 005: `ModuleResolver` — composer-driven transitive module resolution

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Build the engine that turns a set of ROOT package names into the full, boot-ordered set of marko/markommerce modules, by reading composer's `installed.json` and transitively following each package's `require`. Each resolved module exposes its install path (for entity discovery) and its `module.php` manifest data. This powers both `StoreProfile::of(...)` and `fromInstalled()`.

## Context
- Read `vendor/composer/installed.json` (CONFIRMED structure): entries have `name`, `require{}` (package→constraint), `extra.marko.module` (true for modules), `autoload.psr-4`, `install-path` (relative from `vendor/composer/`), `dist.type: path` (symlinked monorepo repos). Resolve `install-path` to an absolute real path.
- API (in `packages/testing/src/Module/`):
  - `resolveFrom(array $rootPackageNames): array` → transitive set, filtered to marko-module packages (`extra.marko.module === true`), as a structure carrying: package name, absolute path, `require[]`, and whether a `module.php` exists.
  - `resolveAllInstalled(): array` → every installed marko module (for `fromInstalled()`).
- Build `Marko\Core\Module\ModuleManifest` objects for resolved modules (name, version, **path** = absolute install path, **require** = the marko-module deps, boot/bindings/singletons loaded from `module.php` if present).
- **CONFIRMED reusable: `Marko\Core\Module\ModuleDiscovery`** (`marko/packages/core/src/Module/ModuleDiscovery.php`). `discoverInVendor(string $vendorDir): array<ModuleManifest>` filesystem-scans `vendor/*/*`, filters to marko modules (`isMarkoModule`), and builds FULL `ModuleManifest`s (path, source, AND bindings/singletons/boot parsed from `module.php` via its `ManifestParser`) — strictly better than the Tier2 hand-rolling. **Use it for `resolveAllInstalled()` directly** (pass the app's `vendor/` dir; symlinked path repos resolve through it). NOTE: `discoverInVendor` does NOT read `installed.json` and does NOT do transitive subset filtering — it returns ALL installed marko modules. **For `resolveFrom([roots])`, layer transitive filtering on top**: read `installed.json` (or the discovered manifests' `require`) to compute the transitive marko-module closure of the root set, then return only those manifests. Either drive the closure from `installed.json` `require{}` or from the `ModuleManifest->require` of the discovered set — pick one and be consistent.
- The `require` walk must skip non-module packages and platform reqs (`php`, `ext-*`, marko framework packages that aren't markommerce modules unless they declare `extra.marko.module`).
- Boot ordering is task 006's job (DependencyResolver); this task just produces the manifest set with correct `require` populated so the resolver can order them.
- **Monorepo symlink caveat**: `install-path` for path repos points back into `packages/*`; resolve to a canonical absolute path so `src/Entity` and `module.php` are found. Verify.

## Requirements (Test Descriptions)
- [x] `it reads installed marko modules from composer installed json`
- [x] `it resolves the transitive marko module set from a single root package`
- [x] `it excludes non-module packages and php platform requirements from the set`
- [x] `it resolves the absolute install path for each module`
- [x] `it builds module manifests with require populated for dependency ordering`
- [x] `it resolves the full installed module set for fromInstalled`
- [x] `it includes a module.php boot closure when the package provides one`

## Acceptance Criteria
- `resolveFrom([...])` returns the correct transitive marko-module closure with absolute paths + manifests.
- `resolveAllInstalled()` returns every installed marko module.
- Works with monorepo symlinked path repos (paths resolve to real `packages/*` dirs).
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

- `ModuleResolver` lives at `packages/testing/src/Module/ModuleResolver.php`.
- `resolveAllInstalled()` delegates entirely to `ModuleDiscovery::discoverInVendor($vendorDir)`.
- `resolveFrom()` layers transitive filtering on top: reads `vendor/composer/installed.json` to walk require edges, keeps only packages present in the `discoverInVendor` set (guaranteeing `extra.marko.module = true`).
- Platform reqs (`php`, `ext-*`, `lib-*`) are skipped in the BFS walk.
- `vendorDir` is injectable (constructor arg with no default) — tests pass `dirname(__DIR__, 5) . '/vendor'`.
- PHPStan level 8 clean (verified with `php -d memory_limit=2G`).
- 13 pre-existing test failures (ViteManifestException in demo packages) are unrelated to this task.
