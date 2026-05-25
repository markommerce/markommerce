# Task 004: NoChangelogTest + Delete Per-Package CHANGELOGs

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Write `tests/PackageStandard/NoChangelogTest.php` asserting no `packages/*/CHANGELOG.md` exists. Delete the two existing per-package changelogs (`packages/scope/CHANGELOG.md`, `packages/scope-pgsql/CHANGELOG.md`) to bring the test green. This matches marko upstream convention (0 of 80 packages have per-package changelogs).

Also drop the CHANGELOG-asserting tests in the two scope ReadmeTest files — they file_get_contents() the deleted CHANGELOG.md and will fail with a PHP warning + null content otherwise.

## Context

The decision (documented in task 001) is that markommerce does not maintain per-package changelogs. Release notes live elsewhere (GitHub releases, root CHANGELOG, or similar) — out of scope for this plan.

- Related files:
  - `packages/scope/CHANGELOG.md` — to delete
  - `packages/scope-pgsql/CHANGELOG.md` — to delete
  - `packages/scope/tests/Unit/ReadmeTest.php` — has 3 `it(...CHANGELOG.md...)` blocks (lines 52-73) referencing the file by absolute path; remove those `it()` blocks
  - `packages/scope-pgsql/tests/Unit/ReadmeTest.php` — has 3 `it(...CHANGELOG.md...)` blocks (lines 65-78 and 125-144) referencing the file; remove those `it()` blocks
- Patterns to follow: marko upstream packages have no CHANGELOG.md

## Requirements (Test Descriptions)

- [x] `it asserts no packages/*/CHANGELOG.md file exists`

## Acceptance Criteria
- `tests/PackageStandard/NoChangelogTest.php` exists and follows Pest 4 syntax
- `packages/scope/CHANGELOG.md` is deleted
- `packages/scope-pgsql/CHANGELOG.md` is deleted
- CHANGELOG-asserting `it()` blocks removed from `packages/scope/tests/Unit/ReadmeTest.php` and `packages/scope-pgsql/tests/Unit/ReadmeTest.php` (3 blocks each); the rest of those files stays intact
- `composer test` passes the new test
- No regressions in other tests

## Implementation Notes
- Created `tests/PackageStandard/NoChangelogTest.php` using `glob()` to find `packages/*/CHANGELOG.md` and asserting the result is empty.
- Deleted `packages/scope/CHANGELOG.md` and `packages/scope-pgsql/CHANGELOG.md`.
- Removed 3 CHANGELOG-asserting `it()` blocks from `packages/scope/tests/Unit/ReadmeTest.php` (lines 52-73).
- Removed 3 CHANGELOG-asserting `it()` blocks from `packages/scope-pgsql/tests/Unit/ReadmeTest.php` (2 at lines 65-78 and 1 at lines 125-144).
- Pre-existing failures in `ExceptionsNamingTest` (layout package naming) are unrelated to this task.
