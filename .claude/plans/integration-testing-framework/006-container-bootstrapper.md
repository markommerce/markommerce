# Task 006: `ContainerBootstrapper` — preferences + container + ordered boot

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Abstract the manual container-build-and-boot dance that the Tier2/Tier3 end-to-end tests currently copy-paste into a single reusable bootstrapper: given a set of resolved `ModuleManifest`s + a config repository + the test connection, discover `#[Preference]`s, build the `Container`, register bindings/singletons, resolve boot order, and call each module's boot closure (incl. plugin/interceptor wiring for Tier3-style modules).

## Context
- Replicate VERIFIED setup from `packages/config-scope/tests/Feature/Tier2EndToEndTest.php` (`buildTier2ConfigScopeContainer` / `bootTier2ConfigScope`):
  1. `PreferenceDiscovery::discoverInModule($manifest)` for each manifest with a non-empty path → register `original→replacement` in a `PreferenceRegistry`.
  2. `new Container($preferenceRegistry)`; `instance()` the core singletons: `ContainerInterface`, `PreferenceRegistry`, `ConfigRepositoryInterface` (the config built in task 007), `ConnectionInterface` (the per-profile test connection), `ProjectPaths`, `ModuleRepositoryInterface` as needed.
  3. For each `module.php`: register `singletons[]` via `singleton()`, `bindings{}` via `bind()`.
  4. `DependencyResolver::resolve($manifests)` → ordered; `$container->call($manifest->boot)` for each with a boot closure.
- **Plugin/interceptor wiring is REQUIRED (not optional)** — task 013 migrates the Tier3 end-to-end test onto this bootstrapper, so plugins must work. CONFIRMED sequence from `packages/catalog-market/tests/Feature/Tier3EndToEndTest.php:98-115`: `$registry = new PluginRegistry(); $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator()); $container->setPluginInterceptor($interceptor); $container->instance(PluginInterceptor::class, $interceptor); $container->instance(PluginRegistry::class, $registry);` then `PluginDiscovery::discoverInModule($manifest)` per manifest with a path to register plugins. **CRITICAL ORDERING GOTCHA**: the `PluginInterceptor` MUST be wired (and plugins discovered/registered) BEFORE any plugin-decorated service is resolved from the container — otherwise interception silently does nothing and the Tier3 delete-guard assertion becomes meaningless. Implement plugin wiring as a distinct, clearly-named phase of the bootstrap (e.g. `wirePlugins()`), run after bindings/preferences but before boot closures resolve services. Keep it as its own method/concern within this task so it is testable in isolation; do not gate it behind a flag for Tier3-style profiles.
- Live in `packages/testing/src/Container/ContainerBootstrapper.php`. API roughly: `build(array $manifests, ConfigRepositoryInterface $config, ConnectionInterface $conn): Container` and `boot(Container $container, array $manifests): void` — or a single `bootedContainer(...)`.
- The `ConnectionInterface` binding MUST be an `instance()` binding of the EXACT connection object the lifecycle hands in (task 008), not a fresh `bind()` factory — repositories AND the test's isolation transaction must share ONE connection instance for rollback to isolate. config-pgsql/config-scope-pgsql `module.php` bind their storage via closures that resolve `ConnectionInterface` FROM the container (`packages/config-pgsql/module.php:15-16` confirmed), so binding the connection as a shared instance propagates correctly to all storage.
- **Process module.php bindings/singletons for ALL resolved modules, even path-less ones.** The Tier2 reference test built config-pgsql/config-scope-pgsql manifests WITHOUT a path and hand-bound their storage closures; the ModuleResolver (task 005) instead loads each module.php and populates `bindings`/`singletons`/`boot` on the manifest, so the bootstrapper should apply those automatically (this is cleaner than the manual Tier2 wiring and removes the hand-bound storage closures). Do NOT skip a module's bindings just because its preference-discovery `path` scan is empty.
- **Preference-skip gotcha (CONFIRMED in Tier2)**: `config-scope/module.php` supplies an explicit factory for `ConfigResolver::class` (wrapping `ScopedConfigResolver` in `ScopedCachingConfigResolver`). If the `ConfigResolver`/`CachingConfigResolver` Preferences are registered, the container applies them BEFORE the factory binding, bypassing the caching wrapper. The bootstrapper must reproduce Tier2's behavior: skip registering Preferences whose `replaces` is `ConfigResolver::class` or `CachingConfigResolver::class` so the explicit factory wins. Also reproduce Tier2's explicit `ConfigWriterInterface -> ScopedConfigWriter` rebind (the container only checks Preferences on the initial `$id`, not on a resolved binding string). Provide a clean, generalized way to express "this module's binding wins over a Preference" rather than hard-coding ConfigResolver, OR document the specific exception.
- Order-sensitivity: some bindings intentionally applied last to override (Tier2 applies config-scope bindings last). The `DependencyResolver` topological order should place config-scope after config; preserve resolver order from task 005; don't reorder bindings within a module.

## Requirements (Test Descriptions)
- [x] `it discovers and registers preferences from modules with a path`
- [x] `it builds a container with core singletons bound`
- [x] `it registers bindings and singletons from each module manifest`
- [x] `it boots modules in dependency order`
- [x] `it binds the connection so resolved repositories use the profile database` (group integration-destructive)
- [x] `it applies a preference override so the replacement implementation is resolved`
- [x] `it wires the plugin interceptor before resolving any decorated service`
- [x] `it discovers and registers module plugins from manifests with a path`
- [x] `it wires plugins so a plugin-decorated service method is intercepted` (Tier3-style; group integration-destructive)
- [x] `it skips the ConfigResolver and CachingConfigResolver preferences so the explicit factory binding wins` (reproduces Tier2)

## Acceptance Criteria
- A reusable bootstrapper produces a booted container equivalent to the hand-written Tier2 (and Tier3 plugin) setup.
- Preference overrides and boot order match the manual behavior.
- Connection binding routes to the supplied DB.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

### ContainerBootstrapper API
- `discoverPreferences(array $manifests): PreferenceRegistry` — discovers preferences from path-having manifests, skipping `ConfigResolver` and `CachingConfigResolver` entries
- `build(array $manifests, ConfigRepositoryInterface, ConnectionInterface): Container` — builds container with core singletons, applies bindings/singletons from all manifests in dependency order, applies ConfigWriterInterface re-bind
- `wirePlugins(Container, array $manifests): void` — wires PluginInterceptor + PluginRegistry, discovers plugins from path-having manifests; MUST be called before any plugin-decorated service is resolved
- `boot(Container, array $manifests): void` — resolves dependency order via DependencyResolver, calls each boot closure
- `bootedContainer(array $manifests, ConfigRepositoryInterface, ConnectionInterface): Container` — convenience combining build + wirePlugins + boot

### Key implementation decisions
- Bindings applied in dependency order (DependencyResolver topological sort) so config-scope's explicit ConfigResolver factory overwrites config's factory
- `ModuleManifest::$singletons` PHPDoc says `array<string, string|Closure>` but module.php files use list syntax with integer keys at runtime; handled via `ctype_digit((string) $key)` to detect numeric keys
- ConnectionInterface bound as `instance()` so all resolved repositories share the same connection (required for test isolation via rollback)
- ConfigWriterInterface re-bind applied when `ScopedConfigWriter` is available and `ConfigWriter` has a registered Preference, reproducing Tier2's manual override

### Test fixtures created
- `tests/Fixture/Container/NullConnection.php` — no-op `ConnectionInterface` for unit tests that don't need a real DB
- `tests/Fixture/Container/NullConfigStorage.php` — no-op `ConfigStorageInterface` for unit tests
- `tests/Fixture/Container/NullScopedConfigStorage.php` — no-op `ScopedConfigStorageInterface` for unit tests
