# Task 001: Update LayoutDiscovery to Scan `resources/views/layout/`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Change `LayoutDiscovery` so that, for each Marko module, it scans `{module}/resources/views/layout/` for layout files and `{module}/resources/views/layout/extensions/` for extension files. The old `{module}/layout/` path is no longer recognised. Update the unit test fixtures to match.

## Context
- Production file: `packages/layout/src/Discovery/LayoutDiscovery.php` — the single line at `:29` defines `$layoutDir`.
- Test file: `packages/layout/tests/Unit/Discovery/LayoutDiscoveryTest.php` — multiple helper functions build temporary fixtures under `{tmp}/layout/...`. All `'/layout'` / `'/layout/extensions'` / `'/layout/...filename'` literals must shift to `'/resources/views/layout'` / etc.
- The behaviour and return shape of `LayoutDiscovery::discover()` does not change — only the directory it reads.
- Failure cases must still raise `InvalidLayoutFileException` exactly as before.

## Requirements (Test Descriptions)
- [ ] `it discovers layout files in resources/views/layout of a module`
- [ ] `it discovers extension files in resources/views/layout/extensions of a module`
- [ ] `it skips modules with no resources/views/layout directory`
- [ ] `it skips a layout directory that has no extensions subdirectory`
- [ ] `it throws InvalidLayoutFileException when a file under resources/views/layout returns a non-Layout value`
- [ ] `it throws InvalidLayoutFileException when a file under resources/views/layout/extensions returns a non-LayoutExtension value`
- [ ] `it does not discover layouts placed in the legacy {module}/layout directory`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/layout/src/Discovery/LayoutDiscovery.php` references only the new path.
- No legacy `'/layout'` directory literal remains in `LayoutDiscovery` or its test.
- `./vendor/bin/phpstan analyse packages/layout/src/Discovery/LayoutDiscovery.php` passes at level 8.

## Implementation Notes
- The negative-case test (`it does not discover layouts placed in the legacy {module}/layout directory`) must construct a temp module that contains **both** a legacy `{module}/layout/` directory (with a `.php` file that would parse as a `Layout`) **and** an empty or unrelated `{module}/resources/views/layout/` directory, then assert the legacy file is not returned by `discover()`. Do not reuse the `makeTempModuleDir` helper for this — create a separate fixture builder so the helper continues to express the supported convention only.
- All existing helper functions (`makeTempModuleDir`, `writeLayoutFile`, `writeExtensionFile`, `writeWrongTypeFile`) currently build paths like `$dir . '/layout/...'`. Update each to use `$dir . '/resources/views/layout/...'`. The `mkdir(..., 0755, true)` recursive flag in `makeTempModuleDir` handles the deeper path with no change to the call site.
