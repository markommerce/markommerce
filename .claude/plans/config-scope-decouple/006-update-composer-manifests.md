# Task 006: Drop markommerce/scope from config/composer.json; register the four new packages in root composer.json

**Status**: completed
**Depends on**: 004, 005
**Retry count**: 0

## Description
Remove `markommerce/scope` from `packages/config/composer.json`'s `require` block. Update root `composer.json` to add `markommerce/config-scope`, `markommerce/config-scope-pgsql`, `markommerce/config-locale`, `markommerce/config-market` to the `require` block (alphabetical sort) and to add their `Tests\\` namespaces to `autoload-dev.psr-4`. Verify the existing `repositories.packages/*` path glob picks up the four new package directories (no edit needed there). After this task, `composer install` from the root resolves all new packages from local paths, and `markommerce/config`'s self-contained `composer install` no longer pulls scope.

## Context
- Related files:
  - `packages/config/composer.json` (remove the scope require line)
  - `composer.json` (root — add four new require entries + four new autoload-dev entries)
  - `packages/config-pgsql/composer.json` (verify unchanged — config-pgsql doesn't require scope today)
- Patterns to follow: P4's root composer.json update added three packages in a single edit. Use alphabetical ordering. Add the four `autoload-dev` entries grouped near the existing `Markommerce\\Config\\Tests\\` entry.

## Requirements (Test Descriptions)
- [ ] `it lists markommerce/scope in packages/config/composer.json require block BEFORE this task (sanity check)`
- [ ] `it removes markommerce/scope from packages/config/composer.json require block`
- [ ] `it adds markommerce/config-scope to root composer.json require block`
- [ ] `it adds markommerce/config-scope-pgsql to root composer.json require block`
- [ ] `it adds markommerce/config-locale to root composer.json require block`
- [ ] `it adds markommerce/config-market to root composer.json require block`
- [ ] `it adds Markommerce\\ConfigScope\\Tests\\ pointing to packages/config-scope/tests/ in root autoload-dev psr-4`
- [ ] `it adds Markommerce\\ConfigLocale\\Tests\\ pointing to packages/config-locale/tests/ in root autoload-dev psr-4`
- [ ] `it adds Markommerce\\ConfigMarket\\Tests\\ pointing to packages/config-market/tests/ in root autoload-dev psr-4`
- [ ] `it does NOT add Markommerce\\ConfigScope\\PgSql\\Tests\\ to root autoload-dev (per repo convention; that namespace is declared in packages/config-scope-pgsql/composer.json's local autoload-dev only — mirroring scope-pgsql and config-pgsql)`

## Acceptance Criteria
- All requirements have passing tests (tests can read both composer.json files and assert dictionary keys).
- `composer validate` passes on `packages/config/composer.json` and the root `composer.json`.
- The four added packages do not yet exist on disk (they are scaffolded in tasks 007, 011, 012). The root composer.json edits prepare the autoload paths so subsequent tasks can run their tests from the monorepo root.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
