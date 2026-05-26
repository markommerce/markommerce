# Plan: Scope Metadata Registry Refactor

## Created
2026-05-26

## Status
completed

## Objective
Make `ScopedFieldRegistry` the authoritative source for which entity fields are
scoped by which axes. Attribute-driven declarations (`#[Scoped(axes: […])]`)
remain supported as an ergonomic shortcut for merchant-defined entities, but
they feed the same registry. This unlocks the bridge-package pattern described
in `FEATURES.md` — bridges can register field-to-axis mappings programmatically
at boot, without forking entity classes.

## Related Issues
none

## Discovery Notes

**Current state (verified):**
- `Markommerce\Scope\Attributes\Scoped` is a property attribute carrying `axes: list<string>`.
- `Markommerce\Scope\Metadata\ScopeMetadataFactory` lazily scans entity classes via reflection on first `for($class)` call, reading `#[Scoped]` attributes and validating axes against `ScopeRegistryInterface`.
- `ScopeMetadata` is an immutable value object holding `array<string, list<string>>`.
- Consumers of `ScopeMetadataFactory`: `ScopedOrderByFactory`, `ScopedEntityValidator`, `ScopedOrderBy`, `ScopeResolver`. All call `$factory->for($class)` and treat the result as opaque metadata. None inspect the source of the data.
- The factory is registered as a singleton in `packages/scope/module.php`.
- `Markommerce\Catalog\Entity\Product` and `Markommerce\Catalog\Entity\Category` use `#[Scoped(axes: ['locale'])]` annotations directly. After this plan they keep working unchanged (P2 strips the annotations).

**Target state:**
- New `Markommerce\Scope\Metadata\ScopedFieldRegistry` accumulates property-to-axis mappings. API: `register()`, `axesForProperty()`, `propertiesFor()`, `hasScopedProperties()`. Method names mirror `ScopeMetadata` to keep the public vocabulary consistent (the codebase calls them "properties", not "fields", because `#[Scoped]` is `TARGET_PROPERTY`).
- `ScopeMetadataFactory.for($class)` becomes: lazily scan attributes once per class (writing findings into the registry), then read all metadata for the class from the registry.
- The registry is the single read-time source of truth. Both contribution paths (attributes via lazy scan, bridges via `module.php` boot) end up there.
- Validation happens at registration time: registering against an unknown axis throws `UnknownAxisException` immediately. Registering against an entity class that does not exist throws `UnknownEntityClassException` (new exception, catches typos in bridge `module.php` files).
- An empty axis list passed to `register()` is a no-op (the property is NOT marked as scoped). The attribute scan applies the same rule: a `#[Scoped(axes: [])]` declaration is treated as a no-op rather than "scoped with no axes". This aligns the two contribution paths and fixes the latent inconsistency in the current factory, which marks the property as scoped-with-empty-axes.

**Boot-order requirement:** Scope's own `module.php` boot closure does NOT itself populate axes — `PhpScopeRegistry` is `readonly` and reads `scope.axes` config at construction time. Axes therefore become available the moment the container resolves `ScopeRegistryInterface`, which happens on demand. Bridges that call `$scopedFieldRegistry->register(...)` from their `boot` closure trigger lazy resolution of `PhpScopeRegistry` via the registry's injected `ScopeRegistryInterface`, so axes are guaranteed to be present at registration time. The composer dependency `markommerce/scope` on every bridge ensures the topological sort (`DependencyResolver`) places bridges after scope in boot order — but the real ordering guarantee comes from the container resolving `PhpScopeRegistry` synchronously the first time `hasAxis()` is called.

## Scope

### In Scope
- Create `ScopedFieldRegistry` class with full API and axis validation.
- Refactor `ScopeMetadataFactory` so the registry is the read-time source of truth, fed by both bridge contributions and a lazy attribute scan.
- Wire the registry into `packages/scope/module.php` as a singleton; update `ScopeMetadataFactory`'s constructor signature.
- Sweep all hand-built `new ScopeMetadataFactory(...)` call sites across every package's tests (scope, scope-pgsql, catalog) so they continue to compile and pass after the constructor adds a second parameter.
- End-to-end test demonstrating a synthetic bridge module registering properties via `module.php` boot. The test must exercise the real `ModuleManifest` + `DependencyResolver` + boot-closure-invocation flow used by `Application`, not just a hand-called closure.
- Update `packages/scope/README.md` with a "Field metadata" section covering both contribution paths.

### Out of Scope
- Removing `#[Scoped]` attributes from `catalog`'s `Product`/`Category` entities (P2).
- Decoupling `HasScopes` trait from entities (P2).
- Creating any bridge packages (`catalog-scope`, `catalog-locale`, etc.) — those land in P2.
- Touching `config` (P5).
- DB schema changes (none required).

## Success Criteria
- [ ] `ScopedFieldRegistry` exists with `register` / `axesForProperty` / `propertiesFor` / `hasScopedProperties` methods.
- [ ] `ScopeMetadataFactory` produces correct metadata via the registry for both attribute-annotated entities (existing) and programmatically-registered properties (new).
- [ ] A bridge-style boot callback can register properties and they appear in factory output.
- [ ] Existing `Product` and `Category` metadata behaviour is unchanged from a caller's perspective (no regressions in `catalog` tests).
- [ ] Unknown-axis registration fails loudly at boot with `UnknownAxisException`.
- [ ] Registration against a non-existent class fails loudly at boot with `UnknownEntityClassException`.
- [ ] All hand-built `new ScopeMetadataFactory(...)` call sites across scope, scope-pgsql, and catalog tests are updated to pass the new second argument.
- [ ] All tests passing, including existing scope, scope-pgsql, and catalog test suites (run with `composer test:all`).
- [ ] Code follows project standards (no traits, strict types, readonly where possible, explicit constructor injection).
- [ ] `packages/scope/README.md` documents the new contribution model.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Create `ScopedFieldRegistry` class with axis-validating register API | - | completed |
| 002 | Refactor `ScopeMetadataFactory` to be registry-driven (lazy attribute scan feeds the registry); update its `ScopeMetadataFactoryTest`; sweep every hand-built `new ScopeMetadataFactory(...)` call site across all packages' tests | 001 | completed |
| 003 | Wire `ScopedFieldRegistry` into `packages/scope/module.php`; verify container-resolved factory still produces correct metadata for `Product`/`Category` | 001, 002 | completed |
| 004 | End-to-end test for bridge contribution pattern (synthetic bridge `ModuleManifest` through `DependencyResolver` + boot loop) + unknown-axis loud-failure path | 002, 003 | completed |
| 005 | Update `packages/scope/README.md` to document the registry, both contribution paths, the cache-staleness contract, and the empty-axes-no-op rule | 002, 003, 004 | completed |

## Architecture Notes
- The registry is populated only during boot (axes registered, then bridges contribute, then optionally the first request triggers lazy attribute scans).
- After boot, the registry's `register()` is still callable but should not be invoked at request time — this is a convention, not enforced.
- The factory's per-class cache (`array<class-string, ScopeMetadata>`) stays. Once a class is queried, its metadata is frozen for the process lifetime; this matches the current behaviour.
- `ScopeMetadata`'s public API stays unchanged — all changes are internal to `ScopeMetadataFactory`.
- Validation contract: registering a field with an axis not in `ScopeRegistryInterface` throws `UnknownAxisException::forAxis($axis)`. Same exception type used today.

## Risks & Mitigations
- **Stale per-class cache if registry mutated after first `for()` call.** Mitigation: registry mutations should only happen at boot. Document this in `ScopeMetadataFactory`'s class docblock and in the README. Task 002 includes a test that explicitly locks in the contract: classes queried before a later `register()` keep their old (frozen) metadata; classes queried after see the new metadata. This is acceptable because the lazy attribute scan is also driven from `for()`, so the only realistic "mutation after first read" scenario is operator error in a boot closure that requires deferred work.
- **Boot-order dependency on axes being registered before properties.** Mitigation: `PhpScopeRegistry` is `readonly` and registers axes at construction; the lazy container resolution makes axes available the first time the registry's `hasAxis()` is called. Topo-sorted boot order (via `DependencyResolver`) guarantees `markommerce/scope`'s module is loaded before any bridge that depends on it. Task 004 adds an integration test that drives a synthetic bridge through `DependencyResolver` + boot loop end-to-end.
- **Attribute scan now has a side effect (writes to registry).** Mitigation: clearly documented in `ScopeMetadataFactory`'s class comment; the scan is idempotent (re-registering same axes is a union, no error). The scan respects the empty-axes-no-op rule, so a `#[Scoped(axes: [])]` annotation is silently ignored (was previously stored as scoped-with-no-axes — a latent inconsistency this plan resolves).
- **Constructor signature change to `ScopeMetadataFactory`.** Mitigation: container-resolved consumers resolve automatically. Hand-built `new ScopeMetadataFactory(...)` sites across catalog tests, scope-pgsql tests, and scope's own tests must be swept in task 002. The factory's parameter naming also gets aligned to code-standards (`$scopeRegistry` instead of `$registry`).
- **Method-name vocabulary drift.** Mitigation: `ScopedFieldRegistry` deliberately uses "property" in its API surface to match `ScopeMetadata`'s existing `scopedProperties()` / `axesForProperty()`. The class name keeps "Field" because that is the term `FEATURES.md` uses for the bridge mental model, but every method uses "Property" for codebase consistency.
