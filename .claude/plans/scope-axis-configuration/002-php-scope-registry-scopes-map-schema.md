# Task 002: Parse the Multi-Axis Scopes-Map Configuration Schema in PhpScopeRegistry

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Rewrites `PhpScopeRegistry::buildAxes()` to parse the new axis schema: `'axes' => ['name' => ['default' => 'rootScope', 'scopes' => ['path1' => [], 'path2' => [], …]]]`. The `scopes` key is an associative map keyed by scope path so `ConfigMerger` deep-merges contributions from multiple packages. The `default` key names the axis's root scope. Validates the structure and surfaces loud configuration errors for missing/invalid `default`, empty `scopes`, or non-array shapes.

## Context

This is the headline schema change. The old `'hierarchy' => [indexed list]` form is removed — pre-release, hard break, no compatibility shim. The registry must:

1. Read `definition['scopes']` (associative map) — error if missing, empty, or not an array.
2. Read `definition['default']` (string) — error if missing, error if its value is not a key in `definition['scopes']`.
3. Build a `ScopeHierarchy` via `ScopeHierarchy::fromPaths(array_keys($scopes))` — PHP preserves associative-array insertion order, which is the declaration order after `ConfigMerger` runs.
4. Construct `new ScopeAxis(name: …, hierarchy: …, default: $definition['default'])`.

Existing tests in `PhpScopeRegistryTest` use the old `['hierarchy' => […]]` shape (see `packages/scope/tests/Unit/Registry/PhpScopeRegistryTest.php:80-140`) and must be rewritten to the new schema. The `makeConfigStub` helper at the top of that file can stay; only the test data shape changes.

- Files to modify:
  - `packages/scope/src/Registry/PhpScopeRegistry.php` (rewrite `buildAxes()` and its docblocks)
  - `packages/scope/tests/Unit/Registry/PhpScopeRegistryTest.php` (all axis-construction tests)
  - Any other `scope` package test that passes config arrays through `PhpScopeRegistry` using `['hierarchy' => …]` — locate via `grep -rln "'hierarchy'" packages/scope` and update only the ones feeding `PhpScopeRegistry` (hand-rolled fake `ScopeRegistryInterface` implementations don't read config — leave them be unless their `ScopeAxis` constructor calls are also broken, which task 001 already fixed).
- Patterns to follow:
  - Loud, actionable exception factories from task 001's additions on `ScopeConfigurationException`.
  - `@throws` PHPDoc tags on every method that throws or propagates (CLAUDE.md code standard #8).
  - Narrow `array<string, …>` PHPDoc annotations for the parsed structure.

## Requirements (Test Descriptions)
- [x] `it builds an axis from a scopes map keyed by scope path`
- [x] `it preserves scope declaration order when building the hierarchy`
- [x] `it assigns the configured default scope to the built axis`
- [x] `it throws ScopeConfigurationException when an axis omits the default key`
- [x] `it throws ScopeConfigurationException when the default is not a key in the scopes map`
- [x] `it throws ScopeConfigurationException when the scopes map is empty`
- [x] `it throws ScopeConfigurationException when scopes is not an array`
- [x] `it accepts an empty top-level axes array without error` (regression: `scope.axes => []` must remain valid — the existing `ModulePhpTest` constructs the registry from an empty config to assert the binding wiring)

## Acceptance Criteria
- All requirements have passing tests.
- Every `PhpScopeRegistryTest` case uses the new schema.
- `PhpScopeRegistry` no longer references the string `'hierarchy'`.
- `composer test` is green for the `scope` package after this task. **Do not gate on `composer test:all`** — `scope-pgsql`'s integration test stays red until task 007 lands.
- `phpstan analyse` clean for `packages/scope/`.
- Code follows code standards.

## Implementation Notes

- Rewrote `PhpScopeRegistry::buildAxes()` to read `definition['scopes']` (associative map keyed by path) and `definition['default']` (required string).
- Validates: `scopes` must be an array, must be non-empty, `default` key must exist, and `default` value must be a key in the scopes map.
- Uses `ScopeHierarchy::fromPaths(array_keys($scopes))` to preserve declaration order.
- Old `'hierarchy'` key is fully removed — no compatibility shim.
- Also implemented `ScopeSignatureValidator::validate()` default-scope rejection (calling `forDefaultScope()` factory added in task 001), which was wired up as a failing test in task 001's branch changes but not implemented.
- All 565 tests pass; PHPStan clean on `packages/scope/src/`.
