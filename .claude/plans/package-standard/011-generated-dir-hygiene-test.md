# Task 011: GeneratedDirHygieneTest + Remove Redundant .generated/.gitignore

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Write `tests/PackageStandard/GeneratedDirHygieneTest.php` asserting that no `packages/*/resources/js/.generated/` subdirectory contains a `.gitignore` file (the root `.gitignore` already excludes `packages/*/resources/js/.generated/`). Delete `packages/frontend-demo/resources/js/.generated/.gitignore` to bring the test green.

## Context

Initial RED state — `packages/frontend-demo/resources/js/.generated/.gitignore` exists but is redundant:

Root `.gitignore` already contains:
```
packages/*/resources/js/.generated/
```

So the per-package one is dead code.

- Related files:
  - `packages/frontend-demo/resources/js/.generated/.gitignore` (to delete)
  - `.gitignore` (root — reference only, don't change)
- Patterns to follow: no other package has a per-directory .gitignore in .generated/

## Requirements (Test Descriptions)

- [x] `it asserts no packages/*/resources/js/.generated/.gitignore file exists`

## Acceptance Criteria
- `tests/PackageStandard/GeneratedDirHygieneTest.php` exists and follows Pest 4 syntax
- `packages/frontend-demo/resources/js/.generated/.gitignore` is deleted
- `composer test` passes
- No regressions in other tests

## Implementation Notes
- Used `glob()` to check for the file's existence (not git commands, since .generated/ is gitignored)
- Deleted `packages/frontend-demo/resources/js/.generated/.gitignore` (was redundant — root .gitignore already excludes the entire .generated/ directory)
- Test file: `tests/PackageStandard/GeneratedDirHygieneTest.php`
