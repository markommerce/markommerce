# Task 009: GitkeepHygieneTest + Remove Stale .gitkeeps + Delete Obsolete Per-Package Scaffold Tests

**Status**: completed
**Depends on**: 001, 005
**Retry count**: 0

## Description
Write `tests/PackageStandard/GitkeepHygieneTest.php` asserting that no `.gitkeep` file exists as a sibling of any other tracked file (i.e., `.gitkeep` is allowed only in directories that would otherwise be empty). Remove the stale `.gitkeep` files. Delete `packages/theme-blank/tests/Unit/ComposerManifestTest.php` because it asserts the presence of `.gitkeep` files we're about to remove.

Also delete the other now-redundant per-package scaffolding/manifest tests whose assertions are entirely subsumed by the new root meta-tests in `tests/PackageStandard/` (task 001 + tasks 002-011). Each per-package file below was hand-inspected and confirmed to assert only manifest/scaffold shape that the meta-tests now cover (or that the cleanup invalidates).

## Context

Initial RED state — stale .gitkeeps:
- `packages/frontend/resources/css/.gitkeep` — sibling of `layers.css`
- `packages/frontend-demo/resources/css/.gitkeep` — sibling of `components/counter.css`
- `packages/frontend-demo/resources/views/.gitkeep` — sibling of `counter.latte`, `layout/`
- `packages/theme-blank/resources/css/.gitkeep` — sibling of 30+ CSS files
- `packages/theme-blank/resources/views/layout/.gitkeep` — sibling of 6 latte files
- `packages/theme-blank-demo/resources/views/.gitkeep` — sibling of `layout/`
- `packages/theme-blank/config/.gitkeep` — the only file in the dir; **keep it** (config/ is legitimately empty for theme-blank)
- `packages/theme-blank-demo/resources/css/.gitkeep` — the only file in the dir; **keep it** if css/ is required by convention OR delete the empty dir entirely (decide based on package-standard.md guidance from task 001)

Decision rule: `.gitkeep` is allowed only when its parent dir is otherwise empty AND that dir is required to exist by the standard.

Per-package tests to delete (each verified to be fully redundant with the new meta-tests + would break the cleanup):
- `packages/theme-blank/tests/Unit/ComposerManifestTest.php` — asserts `.gitkeep` presence (lines 124-142) that we are removing, asserts `module.php` returns `[]` (lines 61-69) that we are deleting in task 005, plus standard scaffold shape covered by `ComposerJsonShapeTest` and `FilePresenceTest`
- `packages/frontend-demo/tests/Unit/ComposerManifestTest.php` — fully covered by `ComposerJsonShapeTest`; still passes after cleanup but is now duplicate
- `packages/theme-blank-demo/tests/Unit/ComposerManifestTest.php` — same; duplicate
- `packages/layout-demo/tests/Unit/ComposerManifestTest.php` — same; duplicate
- `packages/layout/tests/Unit/PackageScaffoldingTest.php` — duplicate of `ComposerJsonShapeTest`; the `.gitignore var/` check (lines 69-81) is unrelated to package shape — keep that one `it()` block by extracting it to a fresh `packages/layout/tests/Unit/GitignoreVarTest.php` before deletion
- `packages/frontend/tests/Unit/ComposerManifestTest.php` — covered by `ComposerJsonShapeTest`; the module.php `it()` block is removed by task 005 already; remainder is duplicate
- `packages/catalog/tests/Unit/PackageScaffoldingTest.php` — the `Seed/` PSR-4 assertion in this file is CORRECT (we are keeping the `Seed/` second root, see task 008). The remainder of the file (require/extra/scaffolding shape) is duplicated by `ComposerJsonShapeTest`. Decision: delete the whole file in this task — its content is fully subsumed by the new meta-tests, and we don't want per-package duplicates of standard checks.

Coordination note: task 005 surgically edits some of the files this task deletes wholesale. To avoid merge conflicts, task 009 depends on task 005 completing first — see updated `Depends on` in the task table (`_plan.md`).

- Related files:
  - All `.gitkeep` files listed above
  - 6 per-package test files listed above (delete)
  - 1 new test file to extract from layout: `packages/layout/tests/Unit/GitignoreVarTest.php`
- Patterns to follow: marko upstream uses .gitkeep sparingly and only for legitimately-empty required dirs

## Requirements (Test Descriptions)

- [ ] `it asserts no .gitkeep file under packages/ is a sibling of another tracked file`
- [ ] `it asserts the previously-stale .gitkeep files have been removed`
- [ ] `it asserts the listed obsolete per-package scaffold tests no longer exist`

## Acceptance Criteria
- `tests/PackageStandard/GitkeepHygieneTest.php` exists and follows Pest 4 syntax
- All stale .gitkeep files (those siblings of other tracked content) are removed
- Legitimately-empty required directories keep their .gitkeep
- The following per-package test files are deleted: `theme-blank/tests/Unit/ComposerManifestTest.php`, `frontend-demo/tests/Unit/ComposerManifestTest.php`, `theme-blank-demo/tests/Unit/ComposerManifestTest.php`, `layout-demo/tests/Unit/ComposerManifestTest.php`, `frontend/tests/Unit/ComposerManifestTest.php`, `layout/tests/Unit/PackageScaffoldingTest.php`, `catalog/tests/Unit/PackageScaffoldingTest.php`
- The `var/` `.gitignore` assertion previously living in `layout/tests/Unit/PackageScaffoldingTest.php` is preserved in a new `layout/tests/Unit/GitignoreVarTest.php`
- `composer test` passes
- No regressions in other tests

## Implementation Notes
(Left blank — filled in by programmer during implementation)
