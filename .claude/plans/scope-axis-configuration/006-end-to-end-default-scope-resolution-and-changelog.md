# Task 006: Verify Default-Scope Resolution End-to-End and Add the Scope Changelog Entry

**Status**: pending
**Depends on**: 002, 003, 004, 005
**Retry count**: 0

## Description
Adds a feature test in the `scope` package that wires the shipped `config/scope.php` defaults through `PhpScopeRegistry`, `ScopeContext`, `ScopeResolver`, and `ScopedOrderBy` to prove the headline behaviour: a small store with only default axes resolves scoped properties to the base column and emits `ORDER BY "<column>"` with no `scopes` JSON access. Also updates `packages/scope/CHANGELOG.md` `[Unreleased]` section to document the breaking config-schema change.

## Context

This is the integration check that the framework defaults work as advertised. The test loads the shipped `packages/scope/config/scope.php` (via `require`) and constructs a real `PhpScopeRegistry` from it — not a fake. It then exercises both:

1. **PHP-side resolution** — set up a `HasScopes`-backed entity with a `#[Scoped(axes: ['locale', 'channel'])]` property, leave `ScopeContext` empty (or set it to the declared defaults), call `ScopeResolver::resolved()`, expect the base property value.
2. **SQL-side rendering** — call `ScopedOrderByFactory::create(EntityClass, 'name')->apply($fakeBuilder)` and assert the fake `EntityQueryBuilderInterface` received a plain `orderBy('name', 'ASC')` call, **not** `orderByRaw(...)`. The fake builder records which method was called and with what arguments.
3. **Extension still works** — extend the registry's config in-test with `locale: ['scopes' => ['en' => []]]` (deep-merged via `Marko\Config\ConfigMerger`), set `ScopeContext::in('locale', 'en')`, write an override at `locale:en`, and confirm `resolved()` returns it.
4. **Loud error path** — confirm `ScopeResolver::setOverride()` with a default-scope signature throws `InvalidSignatureForAttributeException` (validates the wiring across tasks 002 and 005).

The fake `EntityQueryBuilderInterface` only needs `orderBy()` and `orderByRaw()` — those are the methods `ScopedOrderBy::apply()` calls. Use the project's "fakes over mocks" convention (testing.md).

The CHANGELOG entry, under `[Unreleased]`:
- **Added**: `default` axis property on `ScopeAxis`; `packages/scope/config/scope.php` shipping default `locale`, `market`, `channel` axes; new `ScopeConfigurationException` factories; `InvalidSignatureForAttributeException::forDefaultScope`.
- **Changed (BREAKING)**: Axis config schema — `hierarchy` (indexed list) replaced by `scopes` (associative map) + required `default` (string). Multi-source contributions deep-merge through `Marko\Config\ConfigMerger`.
- **Changed (BREAKING)**: `SignatureCandidateEnumerator` now filters the axis default out of `walkUp` results; an all-default context produces an empty candidate list and downstream SQL skips the `scopes` JSON column entirely.
- **Changed (BREAKING)**: `ScopeWalker::walkAt()` (and therefore `ScopeResolver::resolvedAt()`) now filters the axis default out of `walkUp` results too, so a stored override at `axis:default` is never returned. The base column IS the default value.
- **Changed (BREAKING)**: Writing an override at a default-scope signature through `ScopeResolver::setOverride()` / `clearOverride()` now throws `InvalidSignatureForAttributeException`.
- **Added**: `Markommerce\Scope\Storage\DefaultScopeGuard` — a static-configured guard that polices direct `HasScopes::setOverride()` / `clearOverride()` calls. `packages/scope/module.php` gains a `boot` callback that wires the guard from the active `ScopeRegistryInterface`. Direct trait writes at a default-scope signature now throw `ScopeStorageException` (new factory `ScopeStorageException::defaultScopeWrite()`).

- Files to create:
  - `packages/scope/tests/Feature/DefaultScopeResolutionTest.php`
  - `packages/scope/tests/Feature/Fakes/RecordingEntityQueryBuilder.php` (or inline the fake inside the test file if small enough)
- Files to modify:
  - `packages/scope/CHANGELOG.md`
- Patterns to follow:
  - Feature tests under `tests/Feature/` exercise multiple components together (testing.md).
  - Hand-rolled fakes over mocks.

## Requirements (Test Descriptions)
- [ ] `it resolves a scoped property to the base column value when the context is entirely at defaults`
- [ ] `it produces a plain order by clause without a scopes json lookup for an all-default context`
- [ ] `it resolves overrides at non-default scopes added after the registry was extended`
- [ ] `it rejects setOverride at a default scope through ScopeResolver`
- [ ] `resolvedAt with a default-scope signature returns the base column value (the findFirstMatch filter)`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/scope/CHANGELOG.md` `[Unreleased]` section lists all four breaking/added items above.
- The feature test loads `packages/scope/config/scope.php` via `require dirname(__DIR__, 2) . '/config/scope.php';` from inside `packages/scope/tests/Feature/DefaultScopeResolutionTest.php` (resolves regardless of cwd; no working-directory assumption).
- `composer test` for the `scope` package is green. **Do not gate on `composer test:all`** — `scope-pgsql`'s integration test stays red until task 007 lands.
- Code follows code standards.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
