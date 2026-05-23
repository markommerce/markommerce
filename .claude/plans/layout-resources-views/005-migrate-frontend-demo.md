# Task 005: Migrate `frontend-demo` Package Layout File

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Relocate the frontend-demo package's layout file from `packages/frontend-demo/layout/` to `packages/frontend-demo/resources/views/layout/`. Remove the now-empty `layout/` directory.

## Context
- File to move:
  - `packages/frontend-demo/layout/demo.php` → `packages/frontend-demo/resources/views/layout/demo.php`
- `packages/frontend-demo/resources/views/layout/` already exists (it contains the `base.latte` Latte template). `LayoutDiscovery` uses `glob('*.php')` so the `.latte` files are ignored; `demo.php` will sit alongside `base.latte` in the same directory.
- Path-hardcoded `require` statements in the frontend-demo test suite must be updated (verified via grep on `'/layout/demo.php'`):
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php:363` — `$layoutPath = dirname(__DIR__, 2) . '/layout/demo.php';` → change to `dirname(__DIR__, 2) . '/resources/views/layout/demo.php'`.
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php:374` — same change.
- The empty `packages/frontend-demo/layout/` directory must be deleted after the move.

## Requirements (Test Descriptions)
- [x] `it loads demo from resources/views/layout/demo.php`
- [x] `it has no files remaining under packages/frontend-demo/layout/`
- [x] `it discovers the frontend-demo demo layout through LayoutDiscovery from the new path`
- [x] `the frontend-demo feature tests still pass when the layout file is at the new path`
- [x] `DemoControllerTest references the layout file at resources/views/layout/demo.php`

## Acceptance Criteria
- `demo.php` lives at the new path; nothing remains at the old path.
- The empty `packages/frontend-demo/layout/` directory is removed.
- `DemoControllerTest.php` references only the new path (two occurrences updated).
- All `packages/frontend-demo/` tests pass under `composer test`.

## Implementation Notes
