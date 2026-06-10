# Task 007: Prune demo / theme / misc Feature tests

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
The demo/theme Feature tests hand-wire a full Router/Container/Latte stack only to assert example-app hardcoded strings — brittle snapshots that break on cosmetic copy/markup changes and test sample content, not shippable product behavior. Several `ModuleBindings`/`ModuleBoot` tests just `require` a php array and assert keys. Collapse to a smoke test + the enable/disable gate, and downgrade pure-assertion tests to Unit.

## Context
- Files:
  - `packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php` (976 ln; lines 403–512 are verbatim duplicates of 539–648)
  - `packages/theme-blank/tests/Feature/LayoutTemplatesTest.php` (Latte-source greps)
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php`
  - `packages/layout-demo/tests/Feature/LayoutDemoControllerTest.php`, `HandleFeatureTest.php`
  - `packages/criteria/tests/Feature/ModuleBindingsTest.php`, `packages/frontend/tests/Feature/ModuleBootTest.php`
- Keep: `packages/layout/tests/Feature/ExtensionPluginTest.php`, `RendererPluginTest.php` (real plugin-interception wiring).

## Requirements (verification assertions about the resulting suite)
- [x] `it collapses ThemeBlankDemoControllerTest to a smoke test plus the 404 gate` — delete the verbatim-duplicate heading tests (403–512 == 539–648) and the per-heading/per-tag/per-button boot tests; keep one 200-renders smoke (asserts a couple representative tags) + the 404-when-disabled gate; relocate the reflection/config tests to `tests/Unit/`.
- [x] `it deletes the Latte-source snapshot tests in LayoutTemplatesTest` — remove the `file_get_contents(...)->toContain('{block ...}'/'{slot ...}')` cases; keep the engine/slot-render cases ("injects content slot", "injects sidebar slots", "each template compiles"); downgrade CSS-content/`package.json exports` asserts to a trivial unit/lint check.
- [x] `it trims DemoControllerTest to the enable/disable gate` — keep enabled-200 + disabled-404; delete the reflection-attribute tests and downgrade the file-content tests to Unit; keep at most one body-markup assertion.
- [x] `it trims layout-demo tests to conditional-handle plus 404` — in `HandleFeatureTest` keep the variant-present/absent pair; in `LayoutDemoControllerTest` keep the 404-when-disabled gate; delete the demo-render scaffolding and the duplicated ~150-line router-builder boilerplate that goes with them.
- [x] `it downgrades criteria ModuleBindingsTest and frontend ModuleBootTest` — move the `require module.php` + array-key assertions to `tests/Unit/`; keep the one container-resolution case in Feature.
- [x] `it leaves layout ExtensionPluginTest and RendererPluginTest intact`.

## Acceptance Criteria
- `composer test` green (these are mostly non-DB); any surviving DB demo test tagged + skip-guarded.
- Removed router-builder boilerplate leaves no orphaned helper functions.
- Relocated files autoload and run under the unit suite.

## Execution (deletion/relocation task — no Red phase)
1. Run `composer test` (these are mostly non-DB) green first; also run each touched package's `tests/` directly.
2. **Depth-shift watch on relocations**: `frontend-demo/DemoControllerTest`, `theme-blank-demo/ThemeBlankDemoControllerTest`, and `layout-demo/*` rely on multi-level `dirname(__DIR__, 2|4)` paths to package root AND to sibling packages (`/../frontend`, `dirname(__DIR__, 4)` base). Top-level `Feature/ → Unit/` is same-depth and preserves these; if any relocated file is nested deeper, recompute. Confirmed no `tests/Unit/ModuleBindingsTest.php` (criteria) collision (verified — only `catalog` has one, different package).
3. When deleting the duplicated ~150-line router-builder boilerplate, grep the file for each removed helper function name to confirm no surviving case still calls it before removal.
4. Re-run `composer test` green; phpcs on touched files.

## Implementation Notes
- ThemeBlankDemoControllerTest: collapsed from 976 lines to 2 tests (smoke + 404). Reflection/config tests relocated to `tests/Unit/ThemeBlankDemoControllerTest.php` (8 tests). Router builder kept in Feature.
- LayoutTemplatesTest: removed 9 Latte-source `{block}`/`{slot}` grep tests and 4 CSS/package.json content tests. CSS/package.json tests relocated to new `tests/Unit/LayoutCssTest.php`. Kept: 3 engine/slot-render tests + 2 compile tests.
- DemoControllerTest: Feature file trimmed to enabled-200 (with `toContain('<markommerce-counter')` body assertion) + disabled-404. All reflection, file-content, and extra HTTP tests relocated to `tests/Unit/DemoControllerTest.php`.
- HandleFeatureTest: kept only the variant-present/absent pair (2 tests). Deleted 3 demo-render tests. Router builder preserved (needed for variant tests); artifact builder preserved (used by router builder).
- LayoutDemoControllerTest: kept only the 404-when-disabled gate with a minimal inline setup (no view/component/artifact boilerplate — middleware short-circuits before rendering).
- criteria/ModuleBindingsTest: array-key assertions moved to `tests/Unit/ModuleBindingsTest.php`; container-resolution case retained in Feature.
- frontend/ModuleBootTest: config-file + reflection + engine tests moved to `tests/Unit/ModuleBootTest.php`; Application boot test retained in Feature.
- All new Unit files discovered automatically by phpunit.xml `packages/*/tests` glob.
- `composer test` passes with only the pre-existing `ScopeDecouplingTest` failure (unrelated).
