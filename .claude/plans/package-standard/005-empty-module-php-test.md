# Task 005: EmptyModulePhpTest + Delete Empty module.php Files

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Write `tests/PackageStandard/EmptyModulePhpTest.php` asserting that if `packages/<name>/module.php` exists, its returned array MUST be non-empty. Delete the four currently-empty module.php files (`frontend`, `frontend-demo`, `theme-blank`, `theme-blank-demo`) to bring the test green. This aligns with `.claude/architecture.md` line 43: *"Only create module.php when needed (bindings, disabling)"*.

Also remove the per-package `it('has an empty module.php returning []', ...)` assertions in the affected ComposerManifestTest files — those tests will fail once the files are deleted.

## Context

Initial RED state — these four module.php files return `[]`:
- `packages/frontend/module.php`
- `packages/frontend-demo/module.php`
- `packages/theme-blank/module.php`
- `packages/theme-blank-demo/module.php`

Confirm that marko's module discovery still works without these files — modules are recognized by the `extra.marko.module: true` flag in `composer.json`, not by `module.php` presence.

Per-package tests that currently assert presence of (or `=== []` on) `module.php` and will break:
- `packages/frontend/tests/Unit/ComposerManifestTest.php` — `it('has an empty module.php returning []', ...)` block (around lines 55-63)
- `packages/theme-blank/tests/Unit/ComposerManifestTest.php` — same assertion (around lines 61-69); this whole file is deleted by task 009, so no extra action needed here for that one

The two `*-demo` modules (`frontend-demo`, `theme-blank-demo`) do NOT have a module.php assertion in their ComposerManifestTest files — verified by re-reading them — so only the `frontend` file needs the surgical edit here. Re-grep before editing to catch any drift:

```bash
grep -rln "module.php" packages/*/tests/
```

- Related files:
  - The 4 module.php files above
  - `packages/frontend/tests/Unit/ComposerManifestTest.php` — surgical removal of the module.php `it()` block
  - `.claude/architecture.md:43` — the source-of-truth rule
- Patterns to follow: `packages/catalog/module.php`, `packages/config/module.php`, etc., which contain real bindings

## Requirements (Test Descriptions)

- [x] `it asserts that if a packages/*/module.php exists, the file returns a non-empty array`
- [x] `it asserts the test passes once the four currently-empty module.php files are deleted`

## Acceptance Criteria
- `tests/PackageStandard/EmptyModulePhpTest.php` exists and follows Pest 4 syntax
- `packages/frontend/module.php`, `packages/frontend-demo/module.php`, `packages/theme-blank/module.php`, `packages/theme-blank-demo/module.php` are deleted
- The `it('has an empty module.php returning []', ...)` block in `packages/frontend/tests/Unit/ComposerManifestTest.php` is removed
- Marko module discovery still loads these packages (run `composer test` to confirm no regressions)
- The full test suite still passes
- No regressions in other tests

## Implementation Notes
- Created `tests/PackageStandard/EmptyModulePhpTest.php` with one `it()` block that globs `packages/*/module.php` and asserts each returns a non-empty array
- Deleted 4 empty module.php files via Docker exec (host-path `rm` silently operated on wrong path due to symlink/mount differences)
- Removed `it('has an empty module.php returning []', ...)` from `packages/frontend/tests/Unit/ComposerManifestTest.php`
- Removed `it('has a module.php returning an empty bindings array', ...)` from `packages/theme-blank/tests/Unit/ComposerManifestTest.php`
- Removed two tests from `packages/frontend/tests/Feature/ModuleBootTest.php` that required/asserted on the now-deleted `frontend/module.php`
- Full test suite passes: 1301 tests, 0 failures
