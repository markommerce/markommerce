# Task 011: Integration tests (EAV rebuild, filter/facet query, live fallback)

**Status**: done
**Depends on**: 007, 008, 010
**Retry count**: 0

## Description
DB-backed integration tests proving the attribute index end-to-end against real Postgres: a rebuild
materializes resolved EAV rows per served signature; a layered-nav-style filter/facet query runs
against the indexed columns; and the live-fallback reader returns correct values whether or not the
index is populated.

## Context
- Pattern for the REAL Postgres harness: the Phase-3 `packages/catalog-attribute-scope/tests/Feature/`
  tests, `packages/catalog-price-index/tests/Feature/`, and `IntegrationTestCase` + `StoreProfile`
  (+ `withLocales`/declared axes, `$store->inScope(...)`). STUDY them.
- Tag `->group('integration-destructive')`; run via `composer test:integration`.
- Build a `StoreProfile` rooted at `markommerce/catalog-attribute-index` + `markommerce/locale`
  (declared `locale` axis with `en`/`de`) + `marko/database-pgsql` + `markommerce/attribute-pgsql`
  (definition/option tables). The index table provisions from the entity. Confirm the exact profile
  builder against existing tests.
- Scenarios:
  - Define a scopable, facetable `select` attribute `color` (`config['axes']=['locale']`) with options;
    set a product's global value `red` + scoped `rot` for `locale:de`; run
    `catalog:attribute-index:rebuild` (or call `AttributeIndexer::rebuildAll`); assert index rows: a
    base (`''`) row with `value_text='red'` and a `locale:de` signature row with `value_text='rot'`.
  - Layered-nav query: `SELECT product_id ... WHERE scope_signature='locale:de' AND attribute_code='color'
    AND value_text='rot'` returns the product; a `GROUP BY value_text` facet count returns the expected counts.
  - Live fallback: with NO index rows for a product (skip indexing it), `IndexedAttributeReader::resolve`
    still returns the correct live value (via `ScopedProductAttributeAccessor`); with rows present it
    returns the indexed value.
  - Multiselect: a `multiselect` attribute with two members produces two rows.

## Requirements (Test Descriptions)
- [x] `it materializes a base row and a per-signature resolved row on rebuild`
- [x] `it returns the product from a scoped value filter query against the index`
- [x] `it produces facet value counts via a group-by query against the index`
- [x] `it returns the live value via the fallback reader when the index has no row for the product`
- [x] `it returns the indexed value via the reader when an index row exists`

## Acceptance Criteria
- Tests pass under `composer test:integration` against real Postgres using harness-provisioned schema.
- Layered-nav filter + facet-count queries run against the typed indexed columns; live fallback proven.

## Implementation Notes
- Created `packages/catalog-attribute-index/tests/Feature/AttributeIndexIntegrationTest.php` with all 5 tests.
- Profile built from `markommerce/catalog-attribute-index` + `markommerce/locale` + `marko/database-pgsql` + `markommerce/attribute-pgsql` with `->withLocales('default', 'en', 'de')`.
- All tests passed immediately because Tasks 007-010 had already fully implemented `AttributeIndexer::rebuildAll`, `IndexedAttributeReader::resolve`, and the index repository — no additional implementation needed.
- 148 total integration tests pass including the 5 new ones.
