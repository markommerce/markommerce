# Task 001: Add Module-Declared Global Middleware Support to Marko Core

**Status**: complete
**Depends on**: none
**Retry count**: 0
**Working directory**: `/home/michal/www/marko/marko/` (separate repo)
**Branch**: `feature/module-global-middleware` (create off `origin/develop` at task start)

## Description
Extend Marko's module manifest schema so packages can declare global HTTP middleware via `module.php`. `Application::discoverGlobalMiddleware()` merges module-declared entries with the existing hardcoded built-ins, sorts by priority, and deduplicates. Backwards-compatible: apps using the current hardcoded list see zero behavior change.

## Context
- Working dir: `/home/michal/www/marko/marko/` — this is a separate git repo. Create branch with: `git -C /home/michal/www/marko/marko checkout -b feature/module-global-middleware origin/develop`. All edits in this task happen inside that repo.
- Tests in marko use PHPUnit (not Pest). Run with `./vendor/bin/phpunit` inside the docker container — see marko's CLAUDE.md for the exact command.
- **Explicit file list to edit** (all paths inside `/home/michal/www/marko/marko/`):
  - `packages/core/src/Module/ModuleManifest.php` — add `public array $globalMiddleware = []` property (typed). It's a `readonly class` so this is a new constructor parameter at the end of the parameter list.
  - `packages/core/src/Module/ManifestParser.php` — extract `globalMiddleware` from the parsed `module.php` array (line ~30-44 of `parse()`) and pass it to the `ModuleManifest` constructor. Verified the parser currently picks keys explicitly — `globalMiddleware` will not flow through automatically.
  - `packages/core/src/Application.php` — has `const GLOBAL_MIDDLEWARE` at line 306 and `discoverGlobalMiddleware()` at line 355. Rewrite `discoverGlobalMiddleware()` to merge built-ins (with assigned priorities) and module-declared entries, sort by priority, deduplicate.
- Pattern reference: existing `module.php` keys `bindings`, `singletons`, `boot` are parsed at `ManifestParser::parse()` and stored as `ModuleManifest` properties.
- **Module entry shape:** `'globalMiddleware' => [Class::class, ['class' => Class2::class, 'priority' => 25]]`. Bare class-string entries get default priority 100.
- **Priority semantics:** lower priority value = runs earlier in the chain.
- **Deduplication tie-breaker:** when the same class appears multiple times, keep the entry from the highest-source-priority module (app > modules > vendor — same source-priority scheme as `BindingRegistry::SOURCE_PRIORITY`). Within the same source, keep the lowest priority value (runs earliest). Built-in `GLOBAL_MIDDLEWARE` const entries count as source `vendor` for tie-breaking.
- **Backwards compat:** built-in `GLOBAL_MIDDLEWARE` (`PageCacheMiddleware`, `SessionMiddleware`, `LayoutMiddleware`) keeps the existing `class_exists()` silent-skip behavior so apps without those packages still boot. Module-declared `globalMiddleware` entries do NOT silently skip — class-not-found is a loud error (see requirement below). This asymmetry is intentional: existing apps see zero change; new declarations must be correct.
- Leave `marko/packages/session/module.php`, `marko/packages/page-cache/module.php`, `marko/packages/layout/module.php` untouched.

## Requirements (Test Descriptions)

- [x] `it accepts globalMiddleware as a flat list of class strings in module.php`
- [x] `it accepts globalMiddleware entries as array with class key and priority`
- [x] `it defaults missing priority to 100`
- [x] `it merges module-declared globalMiddleware with built-in hardcoded list`
- [x] `it sorts merged globalMiddleware by priority ascending`
- [x] `it deduplicates globalMiddleware entries preferring app over modules over vendor source`
- [x] `it deduplicates globalMiddleware within the same source by keeping the lowest priority value`
- [x] `it assigns priority 10 to PageCacheMiddleware 20 to SessionMiddleware 30 to LayoutMiddleware as built-in defaults`
- [x] `it returns class-string array from discoverGlobalMiddleware in priority order`
- [x] `it throws a clear exception with suggestion when a module-declared class does not exist`
- [x] `it throws a clear exception with suggestion when an array-form entry is missing the class key`
- [x] `it throws a clear exception with suggestion when a declared class does not implement MiddlewareInterface`
- [x] `it silently skips built-in GLOBAL_MIDDLEWARE entries when the class does not exist (backwards compat)`
- [x] `it preserves existing GLOBAL_MIDDLEWARE behavior when no modules declare globalMiddleware`
- [x] `ModuleManifest exposes a globalMiddleware property defaulting to empty array`
- [x] `ManifestParser reads globalMiddleware from module.php and passes it to ModuleManifest`

## Acceptance Criteria
- All requirements have passing tests
- PHPStan clean at marko's configured level
- CHANGELOG entry added under marko's `packages/core/CHANGELOG.md` (or equivalent) — single-line feat entry
- No changes to existing `module.php` files in session/page-cache/layout (out of scope per plan)
- Commit message format matches marko's convention: check `git -C /home/michal/www/marko/marko log -5 --oneline` for examples
- PR opened against marko's `develop` branch (or left as an unpushed branch for the user to PR — do NOT push or open the PR automatically; just commit)

## Implementation Notes
- Extracted the middleware resolution logic into a new `GlobalMiddlewareResolver` class (`packages/core/src/Module/GlobalMiddlewareResolver.php`) to keep it independently testable without a full Application boot cycle.
- `GlobalMiddlewareResolver::DEFAULT_BUILT_INS` is a public constant array that replaces the removed `Application::GLOBAL_MIDDLEWARE` private constant. Built-in entries include `skipIfMissing: true` for backwards compat.
- `Application::discoverGlobalMiddleware()` now delegates to `GlobalMiddlewareResolver::resolve()`.
- `ModuleException` gained two new static factory methods: `invalidMiddlewareClass()` and `invalidMiddlewareEntry()`.
- Four pre-existing `ApplicationTest.php` tests that reflected on `Application::GLOBAL_MIDDLEWARE` constant were updated to use `GlobalMiddlewareResolver::DEFAULT_BUILT_INS` instead.
- Committed on branch `feature/module-global-middleware` in marko repo (not pushed — user to PR).
