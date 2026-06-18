# Task 006: `FEATURES.md` + parent READMEs/docs

**Status**: completed
**Depends on**: 001, 002, 003, 004, 005
**Retry count**: 0

## Description
Update the documentation to reflect the collapsed driver packages: `FEATURES.md` inventory, the naming
convention, and the parent package READMEs/docs that previously pointed at a separate `-pgsql` driver.

## Context
- `FEATURES.md` (already refreshed to a 41-package grouped-by-role inventory earlier on this branch — DELTA
  only, do NOT rewrite):
  - Remove the four `-pgsql` rows (`scope-pgsql`, `config-pgsql`, `config-scope-pgsql`, `attribute-pgsql`)
    from the inventory tables → 41 becomes **37** packages (update the "37 packages" intro count).
  - Update the **"Driver / variant packages"** naming-convention bullet: markommerce no longer ships
    per-domain DB driver packages — Postgres is assumed and each domain bundles its Postgres impl. Keep the
    framework note that `marko/database-pgsql` (framework driver) still exists.
  - Fix any tier package COUNTS / lists that named `scope-pgsql` / `config-pgsql` / `config-scope-pgsql`
    (e.g. the Tier-2 headless "+ scope-pgsql, …, config-scope-pgsql" line and the package-count tallies).
- Parent READMEs that referenced their old driver:
  - `packages/scope/README.md` — replace the "install markommerce/scope-pgsql" driver line with "ships its
    PostgreSQL implementation directly" (must stay consistent with the updated scope ReadmeTest from task 001).
  - `packages/config/README.md`, `packages/config-scope/README.md`, `packages/attribute/README.md` — if they
    mention a separate `-pgsql` driver/install step, update to reflect the in-package Postgres impl.
- Docs site (`docs/src/content/docs/packages/`): VERIFIED — four dead pages exist and must be REMOVED:
  `scope-pgsql.md`, `config-pgsql.md`, `config-scope-pgsql.md`, `attribute-pgsql.md`. Also update any parent
  package page (or nav/sidebar/index that lists these) and fold the Postgres-impl note into the parent page.
  Check `docs` for a packages index or `astro.config`/sidebar that references the removed slugs and update it
  so the docs build doesn't 404 on dead links.
- This is documentation-completeness work: tests assert the docs/READMEs exist and contain the required
  sections (mirror existing ReadmeTest patterns); do not assert prose beyond key markers.

## Requirements (Test Descriptions)
- [x] `it lists 37 packages with no -pgsql rows in the FEATURES inventory`
- [x] `it documents that markommerce assumes Postgres and ships no per-domain DB driver packages`
- [x] `it updates the scope README to describe an in-package Postgres implementation`

## Acceptance Criteria
- `FEATURES.md` reflects 37 packages, no `-pgsql` rows, updated naming-convention + tier counts.
- Parent READMEs/docs no longer instruct installing a separate `-pgsql` driver.
- Doc-completeness tests green; phpcs/phpstan unaffected.

## Implementation Notes

- FEATURES.md: changed "38 packages" to "37 packages"; removed `config-scope-pgsql` row from Foundation table; updated `scope`, `config`, `config-scope`, `attribute` type labels from "interface + pgsql driver" to "library + pgsql impl"; updated naming-convention bullet to state markommerce assumes Postgres and ships no per-domain DB driver packages (references `marko/database-pgsql`); updated Tier 2 headless from 15→14 and storefront from 16→15; removed config-scope-pgsql from Tier 2 headless list; updated shipped-packages table and commerce-primitives track row; replaced `config-pgsql` historical reference in P5 row with neutral phrasing.
- P5 tests updated: removed tests asserting `config-scope-pgsql` IS present (those were the OLD P5 additions now reversed); updated Tier 2 headless count assertion to 14 and storefront to 15.
- ConfigScopeDecouplePagesTest: flipped the `config-scope-pgsql.md` existence test from "toBeTrue" to "toBeFalse".
- Docs site: deleted `scope-pgsql.md`, `config-pgsql.md`, `config-scope-pgsql.md`; updated `scope.md`, `config.md`, `config-scope.md`, `catalog.md`, `catalog-scope.md`, `catalog-storefront-scope.md` to remove dead -pgsql package links and driver install steps.
- New tests: `tests/Unit/Docs/RetirePgsqlDriversTest.php` (3 tests for the 3 requirements); `tests/Unit/Docs/PgsqlDocsDeletionTest.php` (asserts 3 dead docs pages are gone).
