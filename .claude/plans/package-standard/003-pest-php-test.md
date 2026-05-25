# Task 003: PestPhpTest + Pest.php Sweep

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Write `tests/PackageStandard/PestPhpTest.php` asserting every package that has a `tests/` directory also has a `tests/Pest.php` file matching the canonical skeleton in `.claude/package-standard.md`. Add the missing Pest.php files to bring the test green. For `core` (which has no tests/ dir at all and is type `library`), create a minimal `tests/` dir with `Pest.php` so it follows the same convention as other packages.

## Context

Initial RED state — these packages are missing Pest.php:
- frontend, frontend-demo, layout-demo, scope-pgsql, theme-blank, theme-blank-demo

`core` currently has no `tests/` directory; this task creates one with the canonical Pest.php and a `tests/Unit/` placeholder structure consistent with other packages (no actual test cases — let later work add them).

Existing Pest.php files are byte-identical empty skeletons. Use that as the template.

- Related files:
  - `packages/scope/tests/Pest.php` — the canonical skeleton (all 5 existing files are identical to this one)
  - `.claude/package-standard.md` — embeds the canonical skeleton (task 001)
- Patterns to follow: any of the existing 5 Pest.php files

## Requirements (Test Descriptions)

- [x] `it asserts every package with a tests/ directory has a tests/Pest.php file`
- [x] `it asserts every tests/Pest.php content matches the canonical skeleton from package-standard.md`
- [x] `it asserts the core package has a tests/ directory and tests/Pest.php (library packages follow the same testing convention)`

## Acceptance Criteria
- `tests/PackageStandard/PestPhpTest.php` exists and follows Pest 4 syntax
- All 12 packages now have `tests/Pest.php` matching the canonical skeleton
- `core` package has a `tests/` directory with `tests/Pest.php` and a placeholder `tests/Unit/` subdir
- `composer test` passes the new test
- No regressions in other tests

## Implementation Notes
- Added `tests/Pest.php` to 6 packages missing it: frontend, frontend-demo, layout-demo, scope-pgsql, theme-blank, theme-blank-demo
- Created `packages/core/tests/` directory with `Pest.php` and empty `tests/Unit/` subdir
- Canonical skeleton stored as module-level `$canonicalPestPhp` variable (heredoc + `\n` for trailing newline) shared via `use` in closures
- `$packagesDir` also extracted as module-level variable to avoid duplication across two test closures
- The heredoc ends with `PHP . "\n"` because actual files have a trailing newline that the heredoc delimiter alone does not produce
