# Task 008: Implement config-scope override storage layer and boot-time #[Scoped] attribute scan

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Add the foundational override-resolution machinery to `markommerce/config-scope`:
1. Define `ScopedConfigStorageInterface` (load/save/delete overrides keyed by `(configKey, signature)`).
2. Implement `InMemoryScopedConfigStorage` (test-grade backing for unit tests).
3. Relocate `OverrideMatcher` from `markommerce/config` into `Markommerce\\ConfigScope\\Resolution`; adapt its signature from `match(ConfigRow, axes, context)` to `match(array $overrides, list<string> $axes, ScopeContext $context)`.
4. Relocate `AxisNotDeclaredException` from `markommerce/config` into `Markommerce\\ConfigScope\\Exceptions`.
5. Replace `module.php`'s placeholder boot closure with a real one that injects `ConfigClassDiscovery` (from config) and `ScopedFieldRegistry` (from scope), iterates discovered config classes, reflects properties for `#[Scoped]` attributes, and registers each `(configClass, property, axes)` triple with the registry. The Scoped attribute axes are validated against the scope registry by `ScopedFieldRegistry::register()` internally.

## Context
- Related files (new):
  - `packages/config-scope/src/Contracts/ScopedConfigStorageInterface.php`
  - `packages/config-scope/src/Storage/InMemoryScopedConfigStorage.php`
  - `packages/config-scope/src/Resolution/OverrideMatcher.php` (relocated + adapted)
  - `packages/config-scope/src/Exceptions/AxisNotDeclaredException.php` (relocated)
  - `packages/config-scope/module.php` (replace placeholder)
  - `packages/config-scope/tests/Unit/Storage/InMemoryScopedConfigStorageTest.php`
  - `packages/config-scope/tests/Unit/Resolution/OverrideMatcherTest.php` (relocated from config)
  - `packages/config-scope/tests/Feature/ScopedFieldScanTest.php`
- Related (read-only):
  - `packages/scope/src/Metadata/ScopedFieldRegistry.php` (interface for `register`)
  - `packages/scope/src/Signature/SignatureCandidateEnumerator.php` (used by relocated OverrideMatcher)
  - `packages/config/src/Discovery/ConfigClassDiscovery.php` (callable from config-scope's boot)
- Patterns to follow: P2's `catalog-locale/module.php` `boot` closure is the canonical pattern for "iterate-known-fields, register-axes". This task generalises it: instead of hardcoding properties, it scans `#[Scoped]` attributes on whatever the merchant has declared.
- `ConfigClassDiscovery::discover()` walks every module in `ModuleRepositoryInterface::all()` and scans its `$module->path . '/src'` directory. The boot closure therefore requires every `ModuleManifest` constructed in the test (and at runtime) to set `path`. The `ScopedFieldScanTest` MUST build a temp module manifest with `path` pointing at a fixture directory that contains the `#[Scoped]` fixture class, register the manifest with the container's `ModuleRepositoryInterface` instance, and only THEN invoke the boot closure. Mirror `packages/config/tests/Feature/ModulePhpTest.php`'s temp-module pattern.

## Requirements (Test Descriptions)
- [ ] `it declares loadOverrides, loadManyOverrides, saveOverride, and deleteOverride methods on ScopedConfigStorageInterface`
- [ ] `it returns an empty array from InMemoryScopedConfigStorage loadOverrides for an unknown config key`
- [ ] `it stores and retrieves an override via InMemoryScopedConfigStorage saveOverride + loadOverrides round-trip`
- [ ] `it replaces an existing override under the same (key, signature) pair when saveOverride is called twice`
- [ ] `it removes a single override via InMemoryScopedConfigStorage deleteOverride leaving other overrides intact`
- [ ] `it returns multiple keys as a nested map from InMemoryScopedConfigStorage loadManyOverrides`
- [ ] `it returns null from OverrideMatcher match when the overrides array is empty`
- [ ] `it returns the matching override value from OverrideMatcher match when a signature matches the candidate list`
- [ ] `it returns null from OverrideMatcher match when no signature matches the current ScopeContext`
- [ ] `it walks every config class returned by ConfigClassDiscovery during boot and registers #[Scoped] properties with ScopedFieldRegistry`
- [ ] `it does not register any axes when a config class has no #[Scoped] property`
- [ ] `it registers compound axes (e.g. #[Scoped(axes: ['locale', 'market'])]) as a list of axis names`
- [ ] `it throws Markommerce\Scope\Exceptions\UnknownAxisException when a #[Scoped] axis on a discovered config class is not registered with the ScopeRegistry (the exception is raised by ScopedFieldRegistry::register and propagates out of the boot closure)`
- [ ] `it auto-injects ConfigClassDiscovery and ScopedFieldRegistry into the boot closure via container::call`
- [ ] `it relocates only AxisNotDeclaredException::forPropertyAndAxis() to Markommerce\ConfigScope\Exceptions; the forAxisOnProperty() factory used by the pre-P5 ConfigRegistryBuilder build-time scan is dropped (no consumer remains)`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/config/src` does not contain `OverrideMatcher.php` or `AxisNotDeclaredException.php` (verified via task 001 — sanity check here).
- `packages/config-scope/src/Resolution/OverrideMatcher.php` exists with the new signature.
- `packages/config-scope/src/Exceptions/AxisNotDeclaredException.php` exists under the new namespace.
- The boot closure scan uses temp-module fixtures (mirror `packages/config/tests/Feature/ModulePhpTest.php`'s "create a temp module with a fixture config class" pattern) so the test is self-contained.
- PHPStan level 8 clean for all new files.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
