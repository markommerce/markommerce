# Task 006: Drop `markommerce/scope` from catalog composer.json + register new packages at monorepo root

**Status**: completed
**Depends on**: 003, 004, 005
**Retry count**: 0

## Description
Two related composer-manifest changes are bundled into this task because both must land before the new packages exist on disk (tasks 002/007/009) for the monorepo `composer install` + Pest discovery to work:

1. Remove `"markommerce/scope": "self.version"` from `packages/catalog/composer.json`. After tasks 003–005, catalog has no remaining source or test references to scope, so dropping the require is a no-op for the codebase but a meaningful change to the dependency contract: a Tier 1 corner-shop merchant can now `composer require markommerce/catalog` without pulling scope/scope-pgsql at all.

2. Update the **root** `composer.json` to:
   - Add `"markommerce/catalog-scope": "self.version"`, `"markommerce/locale": "self.version"`, `"markommerce/catalog-locale": "self.version"` to `require`.
   - Add `"Markommerce\\CatalogScope\\Tests\\": "packages/catalog-scope/tests/"`, `"Markommerce\\Locale\\Tests\\": "packages/locale/tests/"`, `"Markommerce\\CatalogLocale\\Tests\\": "packages/catalog-locale/tests/"` to `autoload-dev.psr-4`.
   - The `repositories.packages/*` glob already covers package discovery — no change there.

Pest needs the test namespaces registered at the root because monorepo `composer install` flattens root `autoload`/`autoload-dev` but does not include each package's own `autoload-dev`. Without these entries, Pest will fail to autoload test classes from the three new packages.

Run `composer update` for the monorepo afterwards to refresh `composer.lock`, then run the full test suite to confirm zero regression.

## Context
- Related files:
  - `packages/catalog/composer.json` — remove the `markommerce/scope` line from the `require` block
  - `composer.json` (root) — add three new packages to `require` and three new test namespaces to `autoload-dev.psr-4`
  - `composer.lock` (root) — regenerate
- Patterns to follow:
  - `packages/payment/composer.json` shape for an interface-only package (catalog is a domain package, not interface-only, but the no-scope shape is illustrative)
  - Root `composer.json` already lists `"Markommerce\\Catalog\\Tests\\"`, `"Markommerce\\Scope\\Tests\\"`, etc. — follow the same shape.
- Verification: monorepo runs `composer test` after this change and all tests pass; further, `composer why markommerce/scope` from the monorepo root should no longer list catalog as a direct dependent.

## Requirements (Test Descriptions)
- [ ] `it has no markommerce/scope entry in the require block of catalog's composer.json`
- [ ] `it has no markommerce/scope-pgsql entry in the require block of catalog's composer.json (it never had one, but assert anyway)`
- [ ] `it lists markommerce/catalog-scope, markommerce/locale, and markommerce/catalog-locale in the root composer.json require block`
- [ ] `it registers Markommerce\\CatalogScope\\Tests\\, Markommerce\\Locale\\Tests\\, and Markommerce\\CatalogLocale\\Tests\\ in the root composer.json autoload-dev.psr-4`
- [ ] `it passes the full catalog test suite after the dependency is removed`
- [ ] `it succeeds composer dump-autoload at the monorepo root after the change`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/catalog/composer.json` is updated and valid JSON.
- Root `composer.json` is updated and valid JSON, with all three new packages in `require` and all three test namespaces in `autoload-dev.psr-4`.
- The monorepo `composer.lock` is regenerated.
- `./vendor/bin/pest packages/catalog/tests` is green.
- `composer dump-autoload -o` resolves all three new packages and registers their test namespaces.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
