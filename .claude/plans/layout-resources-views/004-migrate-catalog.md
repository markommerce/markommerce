# Task 004: Migrate `catalog` Package Layout File

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Relocate the catalog package's single layout-definition file from `packages/catalog/layout/` to `packages/catalog/resources/views/layout/`. Remove the now-empty `layout/` directory. No other code changes are required — `CategoryController` already routes through the layout system without an `#[Layout]` attribute.

## Context
- File to move:
  - `packages/catalog/layout/category_show.php` → `packages/catalog/resources/views/layout/category_show.php`
- The catalog package already has `packages/catalog/resources/views/components/` populated; the new `layout/` directory will sit alongside `components/`.
- Path-hardcoded `require` statements in the catalog test suite must be updated (verified via grep on `'/layout/category_show.php'`):
  - `packages/catalog/tests/Feature/CategoryControllerTest.php:296` — `$layoutPath = dirname(__DIR__, 2) . '/layout/category_show.php';` → change to `dirname(__DIR__, 2) . '/resources/views/layout/category_show.php'`.
  - `packages/catalog/tests/Feature/CategoryLayoutTest.php:64` — `$path = dirname(__DIR__, 2) . '/layout/category_show.php';` → change to `dirname(__DIR__, 2) . '/resources/views/layout/category_show.php'`.
- The empty `packages/catalog/layout/` directory must be deleted after the move.

## Requirements (Test Descriptions)
- [x] `it loads category_show from resources/views/layout/category_show.php`
- [x] `it has no files remaining under packages/catalog/layout/`
- [x] `it discovers the catalog category_show layout through LayoutDiscovery from the new path`
- [x] `the category_show feature test still passes when layout file is at the new path`
- [x] `CategoryControllerTest references the layout file at resources/views/layout/category_show.php`
- [x] `CategoryLayoutTest references the layout file at resources/views/layout/category_show.php`

## Acceptance Criteria
- `category_show.php` lives at the new path; nothing remains at the old path.
- The empty `packages/catalog/layout/` directory is removed.
- `CategoryControllerTest.php` and `CategoryLayoutTest.php` reference only the new path.
- All `packages/catalog/` tests pass under `composer test`.

## Implementation Notes
