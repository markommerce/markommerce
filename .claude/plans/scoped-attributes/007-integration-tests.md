# Task 007: Integration tests (scoped value + label round-trip, Column fallback)

**Status**: pending
**Depends on**: 005, 006
**Retry count**: 0

## Description
DB-backed integration tests proving scoped resolution end-to-end against real Postgres: scoped Json
value round-trips through `ProductRepository` and resolves correctly per active scope (override vs.
global fallback); a scoped option label resolves per scope; and a Column-backed attribute reflects a
native scoped override.

## Context
- Pattern for the REAL Postgres harness: `packages/catalog-market/tests/Feature/Tier3EndToEndTest.php`,
  `packages/attribute-pgsql/tests/Feature/PgSqlAttributeDefinitionRepositoryTest.php`, and the Phase-2
  `packages/catalog-attribute/tests/Feature/ProductAttributeIntegrationTest.php` (`IntegrationTestCase`
  + `StoreProfile::of(...)` + `TestConnection::skipIfUnavailable()`). STUDY them.
- Tag `->group('integration-destructive')`; run via `composer test:integration`.
- **Profile roots — VERIFIED:** the `locale` axis is provided by `markommerce/locale`; the
  `SignatureCandidateEnumerator` silently OMITs any axis NOT declared in the scope registry, so the
  profile MUST root `markommerce/locale` or scoped resolution never matches. Build:
  `StoreProfile::of($vendorDir, 'markommerce/catalog-attribute-scope', 'markommerce/attribute-scope',
  'markommerce/locale', 'markommerce/attribute-pgsql', 'marko/database-pgsql')->withLocales('default',
  'en', 'de')` (the `withLocales` builder declares the `locale` axis and its `en`/`de` paths — confirm
  the exact builder against `StoreProfile`; do NOT hand-roll axis config). `attribute-pgsql` provides
  `attribute_definitions`/`attribute_options`; the new packages' entity dirs provision the
  `scoped_attribute_values` / `scoped_labels` columns via the harness `SchemaProvisioner`.
- Set the active scope via the harness helper `$store->inScope(null, 'de', fn () => ...)` (it calls
  `ScopeContext::in('locale', …)` for declared axes and `clearAll()` afterward — avoids cross-test
  leakage). Do NOT poke the singleton `ScopeContext::in()` directly.
- Scenarios:
  - Define a scopable `text` attribute `color` (`config['axes'] = ['locale']`); set global `red`
    (Phase-2 accessor) + scoped `rot` for `ScopeSignature::fromArray(['locale' => 'de'])` (scoped
    accessor); save product; re-fetch; `resolve` under `inScope(null,'de')` → `rot`, under
    `inScope(null,'en')` (no override) → `red`.
  - Create a `select` attribute with an option; set a scoped label for `locale:de`; resolve the label
    under `locale:de` (override) and `locale:en` (base label).
- **Column-backed scenario — VERIFIED PREREQUISITE / scope decision:** resolving a scoped native
  `name` via `ScopeResolver` requires BOTH (a) `name` registered as scoped on the `locale` axis
  (only `catalog-locale` does this) and (b) a native-field `HasScopes` storage companion (only
  `catalog-scope`'s `ProductScopedOverrides`). Without them, `ScopeResolver::setOverride` throws
  `propertyNotScoped` and `resolved` always returns the base column. Therefore the Column-backed
  integration scenario CANNOT be exercised in the new-packages-only profile. Choose one:
  - (Preferred — keep the soft-integration boundary) Move the Column round-trip into a SEPARATE
    integration test that additionally roots `markommerce/catalog-scope` + `markommerce/catalog-locale`,
    OR cover it as a UNIT test in task 005 (register the property in `ScopedFieldRegistry` + attach a
    `HasScopes` companion, mirroring `scope/tests/Unit/Resolver/ScopeResolverTest.php`). Do NOT add a
    `require` on catalog-scope to the catalog-attribute-scope package itself.
  - Document the prerequisite either way; do not assert a Column override without those bridges present.

## Requirements (Test Descriptions)
- [ ] `it merges the scoped_attribute_values column into the catalog_products table`
- [ ] `it merges the scoped_labels column into the attribute_options table`
- [ ] `it round-trips a scoped Json value and resolves the override under a matching scope`
- [ ] `it resolves the global value when no scoped override matches the active scope`
- [ ] `it resolves a scoped option label under a matching scope and the base label otherwise`

## Acceptance Criteria
- Tests pass under `composer test:integration` against real Postgres using harness-provisioned schema.
- Scoped value + label resolution demonstrated with override-vs-fallback under a real active scope.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
