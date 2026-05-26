# Task 006: Update frontend-demo to require catalog-storefront and catalog-storefront-scope

**Status**: completed
**Depends on**: 003, 005
**Retry count**: 0

## Description
After P3, `markommerce/frontend-demo` still requires `markommerce/catalog` (for the entities/services) and the P2 bridge stack (`catalog-scope`, `locale`, `catalog-locale`). It does NOT yet require `catalog-storefront` or `catalog-storefront-scope` — without those, the demo's storefront route 404s at runtime because the controller, layout, templates, and scoped grid Preference all live in the new packages.

Add both new requires to `frontend-demo/composer.json`. Extend the existing `packages/frontend-demo/tests/Unit/ComposerRequiresTest.php` (created in P2 task 010) with two new assertions. Verify the full frontend-demo test suite stays green.

## Context
- File to modify: `packages/frontend-demo/composer.json` — add `markommerce/catalog-storefront` and `markommerce/catalog-storefront-scope` to `require`, alphabetically within the `markommerce/*` group (after `markommerce/catalog-scope`, before `markommerce/frontend`).
- File to extend: `packages/frontend-demo/tests/Unit/ComposerRequiresTest.php`. Existing tests assert `markommerce/catalog`, `markommerce/catalog-scope`, `markommerce/catalog-locale`, and `markommerce/locale` are required. Add tests for `markommerce/catalog-storefront` and `markommerce/catalog-storefront-scope`.
- Check `theme-blank-demo/composer.json` and `layout-demo/composer.json` — neither references catalog today, so neither needs a new require (P2 task 010 confirmed this). Re-run their test suites as a regression check.
- Run `./vendor/bin/pest packages/frontend-demo` and `./vendor/bin/pest packages/theme-blank-demo` and `./vendor/bin/pest packages/layout-demo` before reporting done.

## Requirements (Test Descriptions)
- [ ] `it adds markommerce/catalog-storefront to packages/frontend-demo/composer.json require block`
- [ ] `it adds markommerce/catalog-storefront-scope to packages/frontend-demo/composer.json require block`
- [ ] `it preserves the existing P2-era requires (markommerce/catalog, markommerce/catalog-locale, markommerce/catalog-scope, markommerce/locale) in frontend-demo`
- [ ] `it passes the full frontend-demo test suite with the new dependency stack`
- [ ] `it passes the theme-blank-demo test suite (regression check; no requires change expected)`
- [ ] `it passes the layout-demo test suite (regression check; no requires change expected)`

## Acceptance Criteria
- All requirements have passing tests in the extended `ComposerRequiresTest.php`.
- All three demo packages pass their own test suites.
- `composer test:all` from monorepo root passes.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
