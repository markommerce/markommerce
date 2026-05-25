# Task 002: FilePresenceTest + Scaffolding Sweep

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Write `tests/PackageStandard/FilePresenceTest.php` asserting every `packages/*/` directory has `composer.json`, `README.md`, `LICENSE`, and `.gitattributes` files whose content matches the templates in `.claude/package-standard.md`. Then add the missing files to every non-compliant package to bring the test green.

## Context

Initial RED state — these packages are missing files:
- LICENSE missing: catalog, core, frontend, frontend-demo, layout, layout-demo, theme-blank, theme-blank-demo
- .gitattributes missing: every package except scope and scope-pgsql
- README missing: core only

`core` is type `library`, not `marko-module`, but the same three files apply.

- Related files:
  - `LICENSE` (repository root) — MIT text to copy verbatim into each package's LICENSE
  - `packages/scope/.gitattributes` — existing canonical content to copy
  - `.claude/package-standard.md` — embeds both files as fenced blocks (task 001)
- Patterns to follow: existing `packages/scope/`, `packages/scope-pgsql/`, `packages/config/`, `packages/config-pgsql/` are the most-compliant references

## Requirements (Test Descriptions)

- [x] `it asserts every packages/*/ has a composer.json file`
- [x] `it asserts every packages/*/ has a README.md file`
- [x] `it asserts every packages/*/ has a LICENSE file`
- [x] `it asserts every packages/*/ LICENSE content matches the canonical MIT text from package-standard.md`
- [x] `it asserts every packages/*/ has a .gitattributes file`
- [x] `it asserts every packages/*/ .gitattributes content matches the canonical content from package-standard.md`

## Acceptance Criteria
- `tests/PackageStandard/FilePresenceTest.php` exists and follows Pest 4 syntax
- All 12 packages now have LICENSE matching the canonical MIT text
- All 12 packages now have `.gitattributes` matching the canonical content
- `core/README.md` exists with a real description (not a placeholder); other packages already have README so leave them alone
- `composer test` passes the new test
- No regressions in other tests

## Implementation Notes
- `tests/PackageStandard/FilePresenceTest.php` created with 6 Pest tests
- Canonical LICENSE and .gitattributes content hardcoded in the test (simpler and correct since the standard is the source of truth)
- LICENSE created for: catalog, core, frontend, frontend-demo, layout, layout-demo, theme-blank, theme-blank-demo (copied from packages/scope/LICENSE)
- .gitattributes created for: catalog, config, config-pgsql, core, frontend, frontend-demo, layout, layout-demo, theme-blank, theme-blank-demo (copied from packages/scope/.gitattributes)
- core/README.md created with a real description about the package's purpose
- All 12 packages now have all 4 required files with correct canonical content
- Content tests use rtrim() to handle trailing newline differences
- Pre-existing test failures (4) are unrelated to this task (frontend/module.php missing, catalog package.json exports, scope CHANGELOG.md)
