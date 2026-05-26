# Task 003: Drop storefront dependencies from catalog and add StorefrontDecouplingTest

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Strip `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`, `marko/routing`, `marko/view`, and `marko/view-latte` from `packages/catalog/composer.json` `require`. Verify whether `marko/config` is still used inside `packages/catalog/src/` (grep imports); if not, drop it too. Add a new `packages/catalog/tests/Unit/StorefrontDecouplingTest.php` that mirrors the existing `ScopeDecouplingTest` shape — it walks `packages/catalog/src/` and asserts no `Markommerce\Layout\`, `Markommerce\Frontend\`, `Markommerce\ThemeBlank\`, `Marko\Routing\`, `Marko\View\` imports remain; and walks `packages/catalog/composer.json` and asserts none of the dropped requires reappear.

After this task, `markommerce/catalog` is a headless domain package — installable and testable without any of the storefront dependencies present.

## Context
- File to modify: `packages/catalog/composer.json` — drop the six (or seven, if `marko/config` is unused) require entries.
- Reference for the test shape: `packages/catalog/tests/Unit/ScopeDecouplingTest.php` (created in P2 task 005) and `packages/catalog/tests/Unit/ComposerManifestTest.php` (created in P2 task 006).
- Verify `marko/config` usage: `grep -rn "Marko\\\\Config\\\\" packages/catalog/src` — if zero matches, drop the require.
- Existing tests still expected to pass:
  - `packages/catalog/tests/Unit/ComposerManifestTest.php` — already asserts `markommerce/scope` absence; extend with the storefront-namespace assertions (no `markommerce/layout`, `markommerce/frontend`, `markommerce/theme-blank`, `marko/routing`, `marko/view`, `marko/view-latte` keys in catalog's `require`).
  - `packages/catalog/tests/Unit/ScopeDecouplingTest.php` — already trimmed in task 002 (the two `preserves … CategoryControllerTest` / `… CategoryLayoutTest` blocks were removed because those files moved out). Verify it still passes here.
- Catalog's `module.php` remains repository bindings only — no changes.
- After dropping requires, run `composer install` (or `composer update markommerce/catalog`) on the monorepo to verify the autoload tree still resolves.

## Requirements (Test Descriptions)
- [ ] `it drops markommerce/layout from packages/catalog/composer.json require`
- [ ] `it drops markommerce/frontend from packages/catalog/composer.json require`
- [ ] `it drops markommerce/theme-blank from packages/catalog/composer.json require`
- [ ] `it drops marko/routing from packages/catalog/composer.json require`
- [ ] `it drops marko/view from packages/catalog/composer.json require`
- [ ] `it drops marko/view-latte from packages/catalog/composer.json require`
- [ ] `it drops marko/config from packages/catalog/composer.json require if and only if no file under packages/catalog/src imports the Marko\\Config\\ namespace`
- [ ] `it has no Markommerce\\Layout\\, Markommerce\\Frontend\\, or Markommerce\\ThemeBlank\\ imports in any file under packages/catalog/src`
- [ ] `it has no Marko\\Routing\\ or Marko\\View\\ imports in any file under packages/catalog/src`
- [ ] `it has no Markommerce\\Layout\\, Markommerce\\Frontend\\, Markommerce\\ThemeBlank\\, Marko\\Routing\\, or Marko\\View\\ imports in any file under packages/catalog/tests`
- [ ] `it extends packages/catalog/tests/Unit/ComposerManifestTest.php with assertions that catalog's composer.json no longer lists markommerce/layout, markommerce/frontend, markommerce/theme-blank, marko/routing, marko/view, marko/view-latte in either require or require-dev`
- [ ] `it passes the full catalog test suite after the requires are dropped`
- [ ] `it succeeds composer dump-autoload at the monorepo root after the change`

## Acceptance Criteria
- All requirements have passing tests in `packages/catalog/tests/Unit/StorefrontDecouplingTest.php` (and extended assertions in `ComposerManifestTest.php` where appropriate).
- Catalog test suite runs green with the dropped packages still installed in the monorepo (`composer install` is unchanged) — the decoupling claim is about *what catalog requires*, not what the monorepo happens to contain.
- `composer test:all` from the monorepo root passes.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
