# Task 008: ComposerJsonShapeTest + Normalize composer.json

**Status**: complete
**Depends on**: 001, 003
**Retry count**: 0

## Description
Write `tests/PackageStandard/ComposerJsonShapeTest.php` asserting every `packages/*/composer.json` has the canonical shape per `.claude/package-standard.md`: license MIT, type `marko-module` or `library`, a PSR-4 autoload root mapped to `src/` (plus an optional second root mapped to `Seed/` for packages shipping marko/database seeders), single PSR-4 autoload-dev root mapped to `tests/`, Pest `allow-plugins` block where Pest is used, and `marko/testing` in `require-dev` for every marko-module that has a `tests/` directory. Update every non-compliant composer.json to bring the test green.

## Context

Initial RED state:
- `marko/testing` missing from require-dev in: config, config-pgsql, scope, scope-pgsql
- `config.allow-plugins.pestphp/pest-plugin: true` missing from: catalog, frontend, frontend-demo, layout, layout-demo, theme-blank, theme-blank-demo (only config, config-pgsql, scope, scope-pgsql have it)
- `core` is `library` type and has no `require-dev` — task 003 will add a `tests/` dir, so core also needs Pest in require-dev and the allow-plugins block

Depends on task 003 because the test reasoning "if the package has tests/, it must have Pest configured properly" relies on the directory layout being final.

**Seed/ second PSR-4 root is allowed, not forbidden.** Marko's seeder discovery (`marko/database/src/Seed/SeederDiscovery::discoverInVendor`) globs `vendor/*/*/Seed` at fixed depth. Packages that ship seeders MUST keep `Seed/` at the package root and declare a secondary PSR-4 root for that namespace. The `ComposerJsonShapeTest` allows exactly one `src/` PSR-4 root and at most one additional `Seed/` PSR-4 root — anything else is a violation. `packages/catalog/` currently complies with this shape; do not change it.

Note: task 009 will later delete several per-package ComposerManifestTest files that overlap heavily with what this task asserts. Those files do NOT block this task — they are still passing tests today and remain valid until 009 runs. The duplication is acceptable for the brief window between task 008 going green and task 009 deleting the duplicates.

- Related files: all 12 `packages/*/composer.json` files
- Patterns to follow: `packages/scope/composer.json` is the closest to canonical — has license, type, PSR-4 single root, Pest config block, autoload-dev — but is still missing marko/testing

## Requirements (Test Descriptions)

- [x] `it asserts every packages/*/composer.json has "license": "MIT"`
- [x] `it asserts every packages/*/composer.json has "type" set to either marko-module or library`
- [x] `it asserts every packages/*/composer.json autoload.psr-4 has exactly one key mapping to src/ AND optionally one additional key mapping to Seed/ (for packages shipping seeders) — no other roots allowed`
- [x] `it asserts every packages/*/composer.json autoload-dev.psr-4 has exactly one key mapping to tests/ (when the package has tests)`
- [x] `it asserts every packages/*/composer.json that uses Pest has config.allow-plugins."pestphp/pest-plugin" set to true`
- [x] `it asserts every marko-module package with a tests/ directory has marko/testing in require-dev`
- [x] `it asserts every package with tests/ has pestphp/pest in require-dev`

## Acceptance Criteria
- `tests/PackageStandard/ComposerJsonShapeTest.php` exists and follows Pest 4 syntax
- Every `packages/*/composer.json` has the canonical shape
- `marko/testing` added to config, config-pgsql, scope, scope-pgsql, core require-dev
- Pest allow-plugins block added to catalog, frontend, frontend-demo, layout, layout-demo, theme-blank, theme-blank-demo, core
- `composer install` runs cleanly (no dependency-resolution errors)
- `composer test` passes
- No regressions in other tests

## Implementation Notes
- All requirements passed immediately for req 1, 2, 3 (all packages already compliant)
- Req 4 failed for `core` (no autoload-dev) — added autoload-dev, require-dev, and config.allow-plugins to core/composer.json
- Req 5 failed for catalog, frontend, frontend-demo, layout, layout-demo, theme-blank, theme-blank-demo (missing allow-plugins block) — added config.allow-plugins to each
- Req 6 failed for config, config-pgsql, scope, scope-pgsql (missing marko/testing) — added to all four
- Req 7 passed immediately (pestphp/pest already present in all packages with tests, including core after req 4 fix)
- All 1248 tests pass, no regressions
