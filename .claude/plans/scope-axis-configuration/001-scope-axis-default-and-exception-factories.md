# Task 001: Extend ScopeAxis with a Default Scope and Add Configuration Exception Factories

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Adds a required `default` property to the `ScopeAxis` value object naming which scope is the axis's root/global value, and adds factory methods on `ScopeConfigurationException` for the validation rules the new `PhpScopeRegistry` schema will enforce. Also updates every existing `new ScopeAxis(...)` call site in the `scope` package's tests so the suite stays green after the signature change.

## Context

`ScopeAxis` is the dumb value object the registry hands out via `getAxis(string)`. Today it carries `name` and `hierarchy` only. The plan defines "being at the default scope ≡ equivalent to the base column", and downstream components (enumerator, validator) need to read the default off the axis. Adding the field here unblocks tasks 002, 004, and 005.

The exception factories added here are consumed by `PhpScopeRegistry::buildAxes()` in task 002. Adding them now (with their own unit tests in `ScopeExceptionsTest`) lets task 002 focus on parsing logic.

- Files to modify:
  - `packages/scope/src/Axis/ScopeAxis.php`
  - `packages/scope/src/Exceptions/ScopeConfigurationException.php`
  - `packages/scope/tests/Unit/ScopeAxisTest.php`
  - `packages/scope/tests/Unit/Exceptions/ScopeExceptionsTest.php`
- Test fakes to update (mechanical `new ScopeAxis(...)` ripple — keep them green; these fakes actually construct `ScopeAxis` instances so they break the moment a required 3rd constructor argument is added):
  - `packages/scope/tests/Unit/Context/ScopeContextTest.php`
  - `packages/scope/tests/Unit/Query/ScopedOrderByFactoryTest.php`
  - `packages/scope/tests/Unit/Query/ScopedOrderByTest.php`
  - `packages/scope/tests/Unit/Resolution/ScopeWalkerTest.php`
  - `packages/scope/tests/Unit/Resolver/ScopeResolverTest.php`
  - `packages/scope/tests/Unit/Signature/SignatureCandidateEnumeratorTest.php`
  - any other `scope` package test that constructs `new ScopeAxis(...)` (verify via `grep -rln 'new ScopeAxis' packages/scope/tests`)
- Strategy for the fake-registry helpers: each `make*Registry()` function takes an `array $axes` mapping name → paths. After this task, adopt a **single convention** for passing per-axis defaults so downstream tasks (004 and 005) can configure them without re-shaping each fake.

  **DO NOT** default to "first path is the default". Existing unit tests write overrides at scopes like `'global'`, `'eu'`, `'es'`, `'b2b'` (frequently the first path in their fake axes). Picking the first path as the default would silently make those overrides unreachable under task 004's filter, masking real regressions.

  **Recommended convention**: introduce a sentinel default path that does not appear in any existing test fixture. Each fake-registry helper gains a second optional parameter `array $defaults = []`. Inside the helper, for each axis whose `$defaults[$name]` is unset, **prepend** a synthetic root path (e.g. `'__test_default'`) to the hierarchy and use it as the axis's default. Existing tests are unaffected (none of their scope strings collide with `'__test_default'`), and tasks 004/005 can pass an explicit default when they need to test the new behaviour. Example:

  ```php
  // Inside the fake registry constructor:
  foreach ($axes as $name => $paths) {
      $default = $defaults[$name] ?? '__test_default';
      if (!in_array($default, $paths, true)) {
          $paths = array_merge([$default], $paths);
      }
      $hierarchy = new ScopeHierarchy($paths);
      $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
  }
  ```

  Apply this convention identically to every fake registry helper updated in this task.
- Test fakes that do **not** instantiate `ScopeAxis` but whose `getAxis()` throws `RuntimeException('Not implemented')` are **safe to leave untouched** in this task — they remain green because no code under test in tasks 001/002/003/004 invokes `getAxis()` on them. Task 005 updates `ScopeSignatureValidatorTest`'s fake; tasks 002/003 do not touch validator-only fakes.
  - For reference (do NOT edit in this task): `packages/scope/tests/Unit/Metadata/ScopeMetadataFactoryTest.php`, `packages/scope/tests/Unit/Validation/ScopedEntityValidatorTest.php`, `packages/scope/tests/Unit/Signature/ScopeSignatureValidatorTest.php`.
- `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php` is **out of scope** for this task — task 007 owns the scope-pgsql side. `composer test` (the default, non-destructive group) must remain green here; `composer test:all` will be temporarily red on scope-pgsql until 007 completes. Use `composer test` (not `composer test:all`) as this task's acceptance gate.
- Patterns to follow:
  - `readonly class` with public-readonly properties (see existing `ScopeAxis`).
  - Static factory methods on `MarkoException` subclasses with `message`/`context`/`suggestion`.

## Requirements (Test Descriptions)
- [x] `it constructs a ScopeAxis with a name, hierarchy, and default scope`
- [x] `it exposes the default scope as a public readonly property`
- [x] `it builds a ScopeConfigurationException when an axis is missing its default scope`
- [x] `it builds a ScopeConfigurationException when the default scope is not declared in the scopes map`
- [x] `it builds a ScopeConfigurationException when an axis declares an empty scopes map`

## Acceptance Criteria
- All requirements have passing tests.
- Every existing `new ScopeAxis(...)` call site in the `scope` package compiles and the test suite (`composer test`) stays green.
- New exception factories carry `message`, `context`, and `suggestion` that name the offending axis and give the merchant an actionable fix.
- `ScopeAxis` remains a `readonly class` value object with no methods beyond the constructor.
- Code follows code standards (declare strict types, typed constants if any, narrow types).
- No decrease in test coverage.

## Implementation Notes
- Added `public string $default` as a third required constructor parameter to `ScopeAxis` (readonly class — property is public readonly automatically).
- Updated all six fake registry helpers in test files to use the `__test_default` sentinel convention with an optional `array $defaults = []` parameter.
- Fixed a pre-existing failure in `ScopedOrderByTest.php` where the anonymous class spy was missing `selectRaw()` and `whereRaw()` abstract method implementations required by `QueryBuilderInterface`.
- Updated `PhpScopeRegistry::buildAxes()` to read `default` from config if present, otherwise falls back to first path (task 002 will add strict validation).
- Added three factory methods to `ScopeConfigurationException`: `missingDefault(string $axis)`, `defaultNotInScopes(string $axis, string $default)`, `emptyScopesMap(string $axis)`.
- All tests pass: 555 passed, 2 skipped (`composer test`).
