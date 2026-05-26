# Task 004: Bridge contribution end-to-end test

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Add an integration test that exercises the bridge-contribution pattern
end to end: a synthetic module's `boot` callback registers properties with
`ScopedFieldRegistry`, and `ScopeMetadataFactory` subsequently produces
metadata reflecting those programmatic registrations. Also verify the
loud-failure path: a bridge boot that registers against an
unknown axis throws `UnknownAxisException`.

This test locks in the contract bridge packages will rely on in P2.

The test MUST drive the bridge boot through the same mechanism
`Marko\Core\Application` uses — construct `ModuleManifest`s for scope
and the synthetic bridge (with the bridge declaring `markommerce/scope`
in its `require` so `DependencyResolver` orders them correctly), then
run the same loop that calls each module's boot closure via
`$container->call($module->boot)`. A test that only hand-invokes
the bridge's boot closure does not exercise the topological-sort or
container-injection contract that bridges in P2 will rely on.

## Context
- Related files:
  - `packages/scope/tests/Unit/ModulePhpPipelineTest.php` — pattern for simulating module boot in tests
  - `packages/scope/src/Metadata/ScopedFieldRegistry.php` — under test
  - `packages/scope/src/Exceptions/UnknownAxisException.php` — expected failure exception
  - `marko/packages/core/src/Module/ModuleManifest.php` — value object used to simulate the bridge module
  - `marko/packages/core/src/Module/DependencyResolver.php` — topological sort used by `Application` to order boot callbacks
  - `marko/packages/core/src/Application.php` (lines 179–185) — the boot loop pattern to mirror
- Use a throwaway test fixture class (any PHP class with at least one property) as the "entity" being registered against. Do NOT use `Product` or `Category` here — those go through the attribute path and the test should isolate the registry path. Place fixtures under `packages/scope/tests/Support/`.
- The synthetic bridge fixture should be expressed as a `ModuleManifest` constructed inline in the test (not as a real package on disk). Its `boot` is a closure that takes `ContainerInterface` (and optionally `ScopedFieldRegistry` via auto-injection — verify both styles work).
- Place the test at `packages/scope/tests/Feature/BridgeContributionTest.php`.

## Requirements (Test Descriptions)
- [x] `it allows a synthetic bridge module's boot closure to register a property via ScopedFieldRegistry`
- [x] `it exposes programmatically-registered properties through ScopeMetadataFactory after the bridge boot completes`
- [x] `it unions axes from a boot-time registration with axes discovered from a Scoped attribute on the same property` *(use a fixture class with one `#[Scoped(axes: ['locale'])]` property and register the same property under a different axis via the bridge — verify both appear)*
- [x] `it throws UnknownAxisException at boot when a registration references an axis that is not in ScopeRegistryInterface`
- [x] `it throws UnknownEntityClassException at boot when a bridge registers against a class name that does not exist (simulates a typo in module.php)`
- [x] `it preserves boot-time registrations across multiple for calls on the same class`
- [x] `it sorts a synthetic bridge after scope in DependencyResolver when the bridge declares markommerce/scope as a require, so the bridge's boot runs with axes available`
- [x] `it auto-injects ScopedFieldRegistry into a bridge boot closure that type-hints it directly (verifies container call() behaviour bridges in P2 will rely on)`

## Acceptance Criteria
- All requirements have passing tests.
- Tests use real `ScopeRegistryInterface` (constructed from a `ConfigRepository` with the test's chosen axes) and a real `ScopedFieldRegistry` instance (not mocks), with at least the `locale` axis registered so the happy-path tests pass.
- A separate test fixture demonstrates the failure path with an unknown axis name (e.g., `nonexistent`). The exception should propagate out of the boot loop (matching the pattern in `ModulePhpPipelineTest`'s `boot closure surfaces InvalidResolverConfigException` test).
- Tests construct `ModuleManifest` instances inline and exercise `DependencyResolver::resolve()` so the topological sort behaviour is part of the contract being locked in.
- Default policy: no production code changes in this task. If a test fails because of missing behaviour, fix it in the relevant earlier task (001/002/003) and re-run — DO NOT patch the production code from inside the task 004 worktree.
- PHPStan level 8 clean. PHP-CS-Fixer and `phpcs` pass.

## Implementation Notes
- Added `Markommerce\Scope\Tests\` PSR-4 namespace to root `composer.json` `autoload-dev` (mirrors the pattern already used for `Catalog`, `Config`, and `Layout` test namespaces).
- Created two fixture classes under `packages/scope/tests/Support/`: `PlainEntity` (no attributes — pure registry path) and `MixedEntity` (has `#[Scoped(axes: ['locale'])]` on `title` — used for union test).
- All 8 tests live in `packages/scope/tests/Feature/BridgeContributionTest.php`.
- Helper functions `buildScopeContainerWithAxes()`, `scopeModuleManifest()`, and `runBootLoop()` inline the full DependencyResolver → boot-loop wiring, mirroring `Application::initialize()` lines 179-185.
- No production code was modified.
- PHPStan level 8 clean; PHP-CS-Fixer and PHPCS pass after running `php-cs-fixer fix` + `phpcbf`.
