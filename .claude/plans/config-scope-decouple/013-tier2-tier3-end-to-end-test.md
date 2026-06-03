# Task 013: Tier 2/3 end-to-end integration test in config-scope

**Status**: completed
**Depends on**: 009, 010, 011, 012
**Retry count**: 0

## Description
Add `packages/config-scope/tests/Feature/Tier2EndToEndTest.php` — a Postgres-backed full-container-boot test that exercises the entire scoped-config stack end-to-end. The test boots scope + scope-pgsql + locale + market + config + config-pgsql + config-scope + config-scope-pgsql + config-locale + config-market, creates the two persistence tables via their emitters, registers a fixture `#[Scoped]` config class via a temp module, then asserts: (a) the container resolves a `ScopedConfigResolver` and `ScopedConfigWriter` thanks to Preferences; (b) `setOverride` round-trips through Postgres and `resolved` returns the override under matching context; (c) resolution falls back to global when context doesn't match; (d) resolution falls back to property default when no row exists; (e) the bridges (`config-locale`, `config-market`) boot cleanly and register no fields.

## Context
- Related files (new):
  - `packages/config-scope/tests/Feature/Tier2EndToEndTest.php`
  - `packages/config-scope/tests/Feature/Helpers/PostgresTestConnection.php` (copy if not present)
  - `packages/config-scope/tests/Feature/Fixtures/TranslatableSiteConfig.php` (fixture config class with `#[Scoped(axes: ['locale'])]`)
- Related (read-only):
  - `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php` (closest analog — copy + adapt)
  - `packages/catalog-market/tests/Feature/Tier3EndToEndTest.php` (manifest list pattern)
  - `packages/scope-pgsql/src/...` (the scope module bindings and table emitter)
- Patterns to follow: P4's Tier3EndToEndTest spelled out the four PluginInterceptor wiring steps; this test does NOT need Plugin interception (no Plugins in P5) but DOES need PreferenceRegistry pre-binding and ModuleManifest `path` set for every manifest so PreferenceDiscovery scans the right `src/` directories.

**PreferenceRegistry wiring (mirror catalog-storefront-scope's `ScopedProductGridComponentTest`):**
1. Build a fresh `PreferenceRegistry`.
2. Run `PreferenceDiscovery::discoverInModule($manifest)` on the `config-scope` manifest (with `path` set to the package root) and the `config-scope-pgsql` manifest.
3. Register every discovered record on the `PreferenceRegistry` via `register(original, replacement)`.
4. Construct the `Container` with the registry passed to the constructor (the registry must exist BEFORE container construction so the swap path is live for every subsequent `get()` call).
5. Bind/instance every singleton/binding from each module's `module.php`. Apply config-scope's `bindings[ConfigResolver::class]` factory LAST so it wins over config's Tier 1 factory.

**ConfigClassDiscovery + fixture wiring:** the fixture config class (`TranslatableSiteConfig`) must live under a directory whose path is registered as a `ModuleManifest` in `ModuleRepositoryInterface::all()`. Options: (a) co-locate it in `packages/config-scope/tests/Feature/Fixtures/` AND make `tests/Feature` a module path (awkward — pollutes test config), OR (b) create a temp module directory at runtime, write the fixture file there, register a synthetic manifest with `path: $tempDir`, and `require_once` the fixture before running the boot closure. Mirror `packages/config/tests/Feature/ModulePhpTest.php`'s temp-module trick. The test cleanup `afterEach` removes the temp dir.

## Requirements (Test Descriptions)
- [ ] `it boots the full module manifest chain (scope, scope-pgsql, locale, market, config, config-pgsql, config-scope, config-scope-pgsql, config-locale, config-market) without throwing`
- [ ] `it resolves ConfigResolver from the container as an instance of ScopedCachingConfigResolver wrapping a ScopedConfigResolver (the binding installed by config-scope/module.php preserves the caching wrap)`
- [ ] `it resolves ConfigWriterInterface from the container as an instance of ScopedConfigWriter (via the ConfigWriter Preference applied after bindings[ConfigWriterInterface => ConfigWriter])`
- [ ] `it resolves the SetCommand::class binding from the container as an instance of ScopedSetCommand (the Preference swaps the class during construction; CommandRegistry stores the parent's class, CommandRunner asks the container for it, container returns the Scoped subclass)`
- [ ] `it persists a scoped override via writer.setOverride and reads it back via resolver.resolved under matching ScopeContext for locale=de`
- [ ] `it falls back to the global value via resolver.resolved when the active ScopeContext does not match any stored override signature`
- [ ] `it falls back to the property default value via resolver.resolved when no global value and no overrides exist`
- [ ] `it removes a single override via writer.unsetOverride and resolver.resolved drops back to the global value`
- [ ] `it persists a market-axis override via setOverride and reads it under a market-axis ScopeContext (requires #[Scoped(axes: ['market'])] fixture)`
- [ ] `it boots config-locale's empty boot closure without registering any field in ScopedFieldRegistry`
- [ ] `it boots config-market's empty boot closure without registering any field in ScopedFieldRegistry`

## Acceptance Criteria
- All requirements have passing tests under the `integration-destructive` group.
- The fixture `TranslatableSiteConfig` lives in `packages/config-scope/tests/Feature/Fixtures/` and is `require`d at the top of the test so `ConfigClassDiscovery` finds it (alternative: provide via temp module path — mirror P4's approach).
- Every `ModuleManifest` in the test sets `path: dirname(__DIR__, N)` so `PreferenceDiscovery` and `ConfigClassDiscovery` scan correctly.
- `PostgresTestConnection` helper exists under `tests/Feature/Helpers/` (copy from catalog-scope or catalog-market).
- Test cleanup drops both `config_values` and `config_value_overrides` tables after each test to keep parallel runs isolated.
- PHPStan level 8 clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
