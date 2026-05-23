# Task 003: Migrate `layout-demo` Layout Files and Tests

**Status**: complete
**Depends on**: 001, 002
**Retry count**: 0

## Description
Relocate all PHP layout-definition and extension files in `packages/layout-demo/` from `layout/` to `resources/views/layout/`. Update the package's path-sensitive tests so they `require` the files at their new location. Remove the now-empty `layout/` directory.

## Context
- Files to move:
  - `packages/layout-demo/layout/default.php` → `packages/layout-demo/resources/views/layout/default.php`
  - `packages/layout-demo/layout/layout_demo.php` → `packages/layout-demo/resources/views/layout/layout_demo.php`
  - `packages/layout-demo/layout/layout_demo_child.php` → `packages/layout-demo/resources/views/layout/layout_demo_child.php`
  - `packages/layout-demo/layout/layout_demo_variant_featured.php` → `packages/layout-demo/resources/views/layout/layout_demo_variant_featured.php`
  - `packages/layout-demo/layout/extensions/layout_demo_extension.php` → `packages/layout-demo/resources/views/layout/extensions/layout_demo_extension.php`
- Tests to update (hardcoded `__DIR__ . '/../../layout/...'` references):
  - `packages/layout-demo/tests/Unit/LayoutFileTest.php` — **four** lines reference `'/../../layout/layout_demo.php'`: one path string at `:13` (used by `file_exists($layoutPath)`) and three `require` statements at `:24`, `:31`, `:39`. Replace the prefix on all four with `'/../../resources/views/layout/layout_demo.php'`.
  - `packages/layout-demo/tests/Unit/ExtensionFileTest.php` — **three** lines reference `'/../../layout/extensions/layout_demo_extension.php'`: one path string at `:11` (used by `file_exists`) and two `require` statements at `:28`, `:41`. Replace the prefix on all three with `'/../../resources/views/layout/extensions/layout_demo_extension.php'`.
- Feature tests under `packages/layout-demo/tests/Feature/` reference the URL `/markommerce/_demo/layout/1` — **do not change**, that is a route, not a filesystem path.
- The empty `packages/layout-demo/layout/` directory (and its `extensions/` subdir) must be deleted after the moves.

## Requirements (Test Descriptions)
- [x] `it loads layout_demo from resources/views/layout/layout_demo.php in LayoutFileTest`
- [x] `it asserts the layout file exists at the new resources/views/layout path`
- [x] `it loads the extension from resources/views/layout/extensions/layout_demo_extension.php in ExtensionFileTest`
- [x] `it has no remaining files under packages/layout-demo/layout/`
- [x] `it discovers the layout-demo layouts through LayoutDiscovery from the new path`
- [x] `it discovers the layout-demo extension through LayoutDiscovery from the new path`

## Acceptance Criteria
- All five PHP files (four layouts + one extension) live at the new path; none remain at the old path.
- The empty `packages/layout-demo/layout/` directory is removed (including its `extensions/` subdir).
- `LayoutFileTest` and `ExtensionFileTest` reference only the new path.
- All `packages/layout-demo/` tests pass under `composer test`.
- Tests under `packages/layout-demo/tests/Feature/` still pass without modification.

## Implementation Notes
- Used `git mv` to move all five PHP files from `layout/` to `resources/views/layout/` (preserving git history)
- Updated path strings in `LayoutFileTest.php` and `ExtensionFileTest.php` to use the new `resources/views/layout/` prefix
- Deleted the empty `layout/extensions/` and `layout/` directories via `rmdir`
- Added `LayoutDiscoveryPathTest.php` with explicit LayoutDiscovery integration tests for requirements 5 and 6
- All 1122 tests pass under `composer test`
