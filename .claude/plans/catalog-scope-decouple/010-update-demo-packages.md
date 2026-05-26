# Task 010: Update demo packages to require the bridge stack

**Status**: completed
**Depends on**: 002, 006, 007, 009
**Retry count**: 0

## Description
**Reality check before this task starts**: as of P1 completion, none of the demo packages (`frontend-demo`, `layout-demo`, `theme-blank-demo`) directly require `markommerce/catalog`, and `grep` finds no catalog code in any of them. The "Tier 2 demo via live container" framing in `_plan.md`'s Discovery Notes describes the *intended* end state, not the current one.

This task therefore has two minimal-scope objectives:

1. **Verify demo tests still pass** after the catalog refactor — running `composer test` against each demo package's test suite. If anything regressed (none expected), fix it.
2. **Make `frontend-demo` a Tier 2 reference shop** by adding `markommerce/catalog`, `markommerce/catalog-scope`, `markommerce/locale`, and `markommerce/catalog-locale` to its `require` block, so a developer who runs the frontend-demo locally sees a Tier 2 wiring (locale axis resolving against catalog entities). This change is documentation-by-example; no new tests are required if the demo currently doesn't exercise catalog flows.

If a demo *does* (now or in future) exercise scope-aware data, any seeder or fixture that previously assumed `Product->scopes` was a property on the parent entity must be updated to instead attach a `ProductScopedOverrides` companion via the catalog repository.

## Context
- Files to inspect / update:
  - `packages/frontend-demo/composer.json` — add the three requires
  - `packages/frontend-demo/Seed/*` (if any) — adjust scope-related fixtures
  - `packages/frontend-demo/tests/**/*Test.php` — fix any broken assertions
  - `packages/theme-blank-demo/composer.json` — inspect; only update if demo references catalog content
  - `packages/layout-demo/composer.json` — same; layout-demo doesn't depend on catalog, so probably no change
- Reference for current state:
  - `packages/frontend-demo/composer.json` currently requires `markommerce/catalog` indirectly via the route registration; check whether explicit catalog require is also needed
  - Run `./vendor/bin/pest packages/frontend-demo` before and after to compare

## Requirements (Test Descriptions)
- [x] `it adds markommerce/catalog to frontend-demo's composer.json require block (was not previously required)`
- [x] `it adds markommerce/catalog-scope to frontend-demo's composer.json require block`
- [x] `it adds markommerce/locale to frontend-demo's composer.json require block`
- [x] `it adds markommerce/catalog-locale to frontend-demo's composer.json require block`
- [x] `it passes the full frontend-demo test suite with the new dependency stack`
- [x] `it passes the theme-blank-demo test suite (regression check; no requires change expected because theme-blank-demo does not depend on catalog)`
- [x] `it passes the layout-demo test suite (regression check; no requires change expected because layout-demo does not depend on catalog)`
- [x] `it adjusts any demo seeder/fixture that previously called setOverride() on a Product or Category to instead attach a ProductScopedOverrides / CategoryScopedOverrides companion (if any such seeders exist in the demos today — verify before assuming)`

## Acceptance Criteria
- All requirements have passing tests.
- All three demo packages pass their own test suites.
- `composer test:all` from monorepo root passes.
- Code follows project standards.

## Implementation Notes
- Verified baseline: all 69 tests in demo packages passed before changes.
- `markommerce/catalog`, `markommerce/catalog-locale`, `markommerce/catalog-scope`, and `markommerce/locale` added to `packages/frontend-demo/composer.json` require block (alphabetically ordered within the markommerce group).
- Created `packages/frontend-demo/tests/Unit/ComposerRequiresTest.php` with 5 tests covering all 4 requires additions and the seeder/fixture verification.
- Confirmed no seeders or fixtures exist in any demo package; no `->setOverride()` calls found in non-test PHP files.
- `theme-blank-demo` and `layout-demo` composer.json files unchanged (no catalog dependency needed).
- Pre-existing failures in `CatalogScopeDecouplePagesTest` (docs pages not yet created) are unrelated to this task.
