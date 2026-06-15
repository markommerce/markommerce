# Task 006: Integration tests (companion round-trip, Column + Json, reserved code)

**Status**: done
**Depends on**: 004, 005
**Retry count**: 0

## Description
DB-backed integration tests proving end-to-end: the `attribute_values` column merges into
`catalog_products`, custom `Json`-backed values and static `Column`-backed values both round-trip
through `ProductRepository`, and reserved-code rejection works with the wired entity-class map.

## Context
- **Pattern for the REAL Postgres harness**: `packages/catalog-market/tests/Feature/Tier3EndToEndTest.php`
  and `packages/attribute-pgsql/tests/Feature/PgSqlAttributeDefinitionRepositoryTest.php`
  (`IntegrationTestCase` + `StoreProfile::of(...)` + `TestConnection::skipIfUnavailable()`). STUDY
  these — they are the true real-DB model. NOTE: `catalog-scope`'s `CompanionPersistenceTest` is
  NOT a real-DB test (it uses an in-memory fake `ConnectionInterface`); only borrow its
  companion-round-trip SHAPE (build product → attachCompanion → save → re-fetch → assert companion),
  not its connection setup.
- Tag tests `->group('integration-destructive')`; run via `composer test:integration`.
- Build the `StoreProfile` via `StoreProfile::of($vendorDir, 'markommerce/catalog-attribute',
  'marko/database-pgsql')`. **The `marko/database-pgsql` driver module is REQUIRED** in the profile
  for the harness to provision a real Postgres schema (verified against existing integration tests;
  omitting it yields a `MissingModuleException`). `catalog-attribute` transitively pulls
  `markommerce/catalog` + `markommerce/attribute`, so `catalog_products` is provisioned WITH the
  merged `attribute_values` column (the companion auto-links via discovery). The custom-attribute
  definition rows also need `attribute_definitions`/`attribute_options` — confirm `attribute-pgsql`
  is in the dependency closure (add it to the profile if the harness does not pull it transitively,
  mirroring `PgSqlAttributeDefinitionRepositoryTest`'s profile). Confirm the exact `StoreProfile::of(...)`
  signature against those tests.
- Flow under test: create a `Product`; use `ProductAttributeAccessor` to set a custom Json value
  (define a custom attribute via the Phase-1 service first, e.g. a `text` `color`) and a static
  Column value (`name` or `priceAmount`); `ProductRepository->save($product)`; re-fetch; assert
  both values via the accessor `get`.
- Also assert: creating a custom `product` attribute with code `sku` is rejected
  (`ReservedAttributeCodeException`) through the wired map.

## Requirements (Test Descriptions)
- [x] `it merges the attribute_values column into the catalog_products table`
- [x] `it round-trips a Json-backed custom value on a product through save and refetch`
- [x] `it round-trips a Column-backed value written via the accessor onto the native column`
- [x] `it preserves decimal precision for a Column-backed priceAmount value`
- [x] `it rejects creating a custom product attribute whose code collides with a native column`

## Acceptance Criteria
- Tests pass under `composer test:integration` against real Postgres using harness-provisioned schema.
- Both backings demonstrated end-to-end; reserved-code rejection verified.

## Implementation Notes
- Test file: `packages/catalog-attribute/tests/Feature/ProductAttributeIntegrationTest.php`
- Profile: `StoreProfile::of(vendorDir, 'markommerce/catalog-attribute', 'markommerce/attribute-pgsql', 'marko/database-pgsql')`
  - `markommerce/attribute-pgsql` is NOT transitively pulled by `catalog-attribute`, so it must be added explicitly.
- Key discovery: the `database/module.php` boot discovers entities from `ProjectPaths->vendor` but in the test container the `ProjectPaths` basePath is a temp dir, so extender auto-discovery fails. Fixed by adding an explicit `linkExtenders(Product::class, [ProductAttributeValues::class])` call to `catalog-attribute/module.php` boot. This is production-correct behaviour: the module that introduces the companion extender should register it.
- All 5 tests pass; full integration suite (137 tests) and unit suite (2168 tests) pass cleanly.
