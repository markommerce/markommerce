# Task 001: Amend `AttributeIndexer` → full per-signature materialization

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Change the Phase-4 attribute indexer to materialize a row for EVERY served signature of a scopable
attribute (plus the base `''` row), removing the "skip-redundant" optimization. This makes
layered-nav facet/filter queries single-signature `WHERE`/`GROUP BY` (every product has a row at the
active signature for a scopable attribute).

## Context
- File: `packages/catalog-attribute-index/src/AttributeIndexer.php`. The skip-redundant block is in the
  private method `runScopedPasses()` (NOT `indexChunk`), inside the per-product/per-def loop:
  ```php
  // Skip-redundant: only emit if differs from the base value.
  $baseValue = $baseValues[$productId][$def->code] ?? null;

  if ($value === $baseValue) {
      continue;
  }
  ```
  REMOVE that comparison + `continue` so a row is always emitted for each served signature. The base pass
  in `indexChunk` still records `$baseValues` (used elsewhere); you may keep collecting `$baseValues` or
  drop it if no longer referenced after the skip is removed — verify nothing else reads it. Non-scopable /
  empty-axes attributes still get ONLY the base `''` row (they are excluded from `runScopedPasses` because
  `buildDefSignatures` returns `[]` for them — unchanged). Keep multiselect explosion + null-value omission.
- IMPORTANT: there is NO existing unit test that asserts "skip when equal to base" — do NOT look for one
  to invert. The existing scopable test (`writes a per-signature row with the scope-resolved value for a
  scopable attribute`) uses a scoped value (`blue`) that DIFFERS from base (`red`), so it still passes
  unchanged. ADD a NEW unit test that proves a scoped row IS written even when the scoped value EQUALS
  the base value (e.g. base `red`, served signature `store:global.us`, no override → expect 2 rows: base
  `''` + `store:global.us`, both `red`). Keep all other existing assertions green.
- The live-fallback reader (`IndexedAttributeReader`) is unaffected — its top candidate signature is
  now always present for scopable attributes; do not change it. Confirm its tests still pass.
- INTEGRATION TEST FALLOUT (must fix): the Phase-4 integration test
  `packages/catalog-attribute-index/tests/Feature/AttributeIndexIntegrationTest.php` asserts
  `expect($rows)->toHaveCount(2)` for `color` (base `red` + `locale:de` `rot`). The profile serves locales
  `en` AND `de` (`withLocales('default','en','de')`, `default` is the axis default → excluded). Under full
  materialization the product ALSO gets a `locale:en` row equal to base (`red`) — so the count becomes 3,
  not 2. UPDATE that assertion to `toHaveCount(3)` and assert the `locale:en` row resolves to `red`
  (the base value), in addition to the existing `''` → `red` and `locale:de` → `rot` assertions. Audit the
  other scenarios in that file for the same count drift and adjust.

## Requirements (Test Descriptions)
- [x] `it writes a scoped-signature row for each served signature of a scopable attribute even when the value equals base` (NEW test)
- [x] `it still writes a differing scoped row alongside the base row` (existing test, unchanged)
- [x] `it still writes only the base row for a non-scopable attribute` (existing test, unchanged)
- [x] `it still emits one row per member for a multiselect value` (existing test, unchanged)
- [x] `it still omits a row when the resolved value is null` (existing test, unchanged)

## Acceptance Criteria
- No skip-redundant: every served signature of a scopable attribute yields a row (even when equal to base); base row always present.
- The catalog-attribute-index unit + Feature suites pass; the Phase-4 integration count assertion is updated
  (`color` now yields base + `locale:en` + `locale:de` = 3 rows, with `locale:en` resolving to the base value).
- The live-fallback `IndexedAttributeReader` and its tests remain green (no change to the reader).

## Implementation Notes
- Removed the skip-redundant block (`$baseValue === $value → continue`) from `runScopedPasses()` in `AttributeIndexer`.
- Also removed the now-dead `$baseValues` collection from the base pass in `indexChunk` — it was only used for the skip comparison.
- Removed `$baseValues` parameter from `runScopedPasses()` signature and all closure captures.
- Added new unit test `it writes a scoped-signature row for each served signature of a scopable attribute even when the value equals base` in `packages/catalog-attribute-index/tests/Unit/AttributeIndexerTest.php`.
- Updated the integration test `materializes a base row and a per-signature resolved row on rebuild` in `packages/catalog-attribute-index/tests/Feature/AttributeIndexIntegrationTest.php`: count changed from 2 → 3, added assertion for `locale:en` → `red`.
- Other integration test scenarios (facet counts, filter queries, reader tests) are unaffected by the count change because they query by a specific `scope_signature` or don't count raw rows.
