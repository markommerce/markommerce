# Task 002: Refactor ScopeMetadataFactory to be registry-driven

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Change `ScopeMetadataFactory::for($entityClass)` so the `ScopedFieldRegistry`
is the read-time source of truth for property metadata. The factory still
scans `#[Scoped]` attributes on first access to each class, but the scan
now *feeds the registry* rather than producing metadata directly. Once a
class has been scanned, subsequent calls read entirely from the registry.

This preserves backward compatibility for entities annotated with
`#[Scoped]` while enabling bridge packages to contribute mappings
programmatically.

This task also sweeps every hand-built `new ScopeMetadataFactory(...)`
call site to the new two-argument constructor signature, and renames the
existing `$registry` parameter to `$scopeRegistry` per the
code-standards interface-naming rule.

## Context
- Related files (read before modifying):
  - `packages/scope/src/Metadata/ScopeMetadataFactory.php` — current implementation; this task rewrites it
  - `packages/scope/src/Metadata/ScopeMetadata.php` — value-object returned by `for()`; public API stays unchanged
  - `packages/scope/src/Attributes/Scoped.php` — the attribute being scanned
  - `packages/scope/tests/Unit/Metadata/ScopeMetadataFactoryTest.php` — existing tests cover the attribute-only path; update to cover the new dual-path behaviour
- Call sites to sweep (every line that constructs the factory directly with the old single-arg signature):
  - `packages/scope/tests/Unit/Metadata/ScopeMetadataFactoryTest.php`
  - `packages/scope/tests/Unit/Query/ScopedOrderByFactoryTest.php`
  - `packages/scope/tests/Unit/Query/ScopedOrderByTest.php`
  - `packages/scope/tests/Unit/Resolver/ScopeResolverTest.php`
  - `packages/scope/tests/Unit/Validation/ScopedEntityValidatorTest.php`
  - `packages/scope/tests/Feature/DefaultScopeResolutionTest.php`
  - `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php`
  - `packages/catalog/tests/Feature/CategoryControllerTest.php`
  - `packages/catalog/tests/Feature/CategoryLayoutTest.php` (two sites)
  - `packages/catalog/tests/Unit/Component/ProductGridComponentTest.php`
  - Run `grep -rn "new ScopeMetadataFactory(" packages/` after refactoring to confirm none remain on the old signature.
- The factory's `for()` method must remain idempotent and cached per class.
- Decision: lazy scan, not eager. The factory has no enumeration of entity classes; it discovers them on demand. The first call for class `X` triggers an attribute scan that registers any `#[Scoped]` findings with `ScopedFieldRegistry`. Subsequent calls for `X` skip the scan.
- Track per-class "scanned" state separately from the metadata cache, so the scan only happens once even if `axesForProperty()` was already populated by a bridge before `for()` was called.
- Empty-axes rule: a `#[Scoped(axes: [])]` declaration on a property is a no-op (the property is NOT marked as scoped). This is a behavioural fix to the current factory, which marks such a property as scoped-with-empty-axes. Document this in the class docblock and add a test that locks the new behaviour in.
- The reflection scan should also traverse parent classes so future entities that inherit `#[Scoped]` properties keep working. The current implementation uses `$reflection->getProperties()`, which already includes inherited properties — keep that behaviour and add a regression test covering an entity that inherits a scoped property from a parent class.

## Requirements (Test Descriptions)
- [x] `it returns metadata reflecting properties previously registered programmatically in ScopedFieldRegistry`
- [x] `it returns metadata for properties annotated with the Scoped attribute on the entity class`
- [x] `it writes attribute-discovered axes into ScopedFieldRegistry on first access to the class`
- [x] `it merges axes (union) when both an attribute and a registry entry declare the same property`
- [x] `it scans each class for attributes at most once across multiple for calls`
- [x] `it scans each class for attributes at most once even when ScopedFieldRegistry was prepopulated for that class before the first for call`
- [x] `it returns cached ScopeMetadata for repeated for calls with the same class`
- [x] `it returns empty ScopeMetadata when the class has neither attributes nor registry entries`
- [x] `it throws UnknownAxisException when an attribute references an axis not registered in ScopeRegistryInterface`
- [x] `it treats a Scoped attribute with an empty axes list as a no-op (the property is not marked as scoped)` *(behaviour change vs. current factory; locks the new contract)*
- [x] `it discovers Scoped properties inherited from a parent class` *(regression test for reflection traversal)*
- [x] `it freezes cached metadata against later registry mutations` — once `for($class)` has been called and a `ScopeMetadata` cached, a subsequent `ScopedFieldRegistry::register()` against the same class does NOT change the cached result *(documents the cache-staleness contract)*
- [x] `it sees registrations made before the first for call when they happen after construction but before the first read`

## Acceptance Criteria
- All requirements have passing tests.
- `ScopeMetadataFactory` constructor takes both `ScopeRegistryInterface $scopeRegistry` and `ScopedFieldRegistry $scopedFieldRegistry` (parameter names per the interface-naming rule — note that for the concrete `ScopedFieldRegistry` the parameter is `$scopedFieldRegistry`, camelCase of the class name).
- The class-level docblock explains: (a) the dual contribution model, (b) the lazy-scan side effect on `ScopedFieldRegistry`, (c) the cache-staleness contract (cached classes are frozen against later registry mutations), (d) the empty-axes-no-op rule.
- No public method signatures change (`for()` returns `ScopeMetadata` as today).
- All existing consumers (`ScopedOrderByFactory`, `ScopedEntityValidator`, `ScopedOrderBy`, `ScopeResolver`) continue to work without changes — they receive the factory via DI.
- All hand-built `new ScopeMetadataFactory(...)` call sites listed in the Context section are updated to construct and pass a real `ScopedFieldRegistry` (with the same `ScopeRegistryInterface` mock used in the test). No test should mock `ScopedFieldRegistry`.
- Running `composer test:all` (full scope + scope-pgsql + catalog test suites) passes with zero regressions.
- PHPStan level 8 clean. PHP-CS-Fixer and `phpcs` pass.
- Every `@throws` documented; exception classes imported.

## Implementation Notes
- Rewrote `ScopeMetadataFactory` to accept two constructor params: `ScopeRegistryInterface $scopeRegistry` and `ScopedFieldRegistry $scopedFieldRegistry`.
- Added a `$scanned` array (separate from `$cache`) to track which classes have had their attributes scanned, so the scan happens exactly once even when the registry is pre-populated.
- The `scanAndRegister()` private method performs the reflection scan and calls `ScopedFieldRegistry::register()` for each discovered property with non-empty axes.
- Empty `axes: []` on a `#[Scoped]` attribute is treated as a no-op (skipped during scan).
- `ReflectionClass::getProperties()` traverses inherited properties automatically.
- Updated all hand-built `new ScopeMetadataFactory($registry)` call sites across scope, scope-pgsql, and catalog packages to the new two-arg form: `new ScopeMetadataFactory($registry, new ScopedFieldRegistry(scopeRegistry: $registry))`.
- PHPStan level 8 clean (Metadata directory); php-cs-fixer and phpcs pass.
- 1405 tests pass with zero regressions.
