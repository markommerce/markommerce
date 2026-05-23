# Task 020: `module.php` final wiring

**Status**: pending
**Depends on**: 011, 013, 016, 017, 018, 019
**Retry count**: 0

## Description
Wire the entire `markommerce/config` package into Marko's DI container via `module.php`. Register service bindings, singletons, register `PreferenceRegistry` as a container instance (Marko does not bind it by default), discover config classes via attribute-scan, register the `ProxyAutoloader` and the `ConfigCacheResetMiddleware`. Match the conventions established by `packages/scope/module.php`.

## Context
- Reference: `packages/scope/module.php` for shape (`bindings`, `singletons`, `boot`, `globalMiddleware`)
- Bindings needed:
  - `ConfigStorageInterface` → bound to `InMemoryConfigStorage` by default (overridden by `markommerce/config-pgsql` in production)
  - `ConfigWriterInterface` → `ConfigWriter`
  - `SecretCipherInterface` → lazy closure factory that constructs `SodiumSecretCipher` only when first invoked. Reads the 32-byte key from `ConfigRepositoryInterface` (env `MARKOMMERCE_CONFIG_SECRET_KEY`, base64-decoded). If the key is unset, the closure throws a setup exception WHEN CALLED — never at boot
  - `ConfigCacheInterface` → `RequestConfigCache` (singleton; reset per request via middleware)
- Singletons:
  - `ConfigRegistry` (built at boot via `ConfigRegistryBuilder` from the discovered config-class list)
  - `ConfigResolver`
  - `ProxyLocator`
  - `ProxyAutoloader` (and registered at boot)
  - `RequestConfigCache`
- Register `Marko\Core\Container\PreferenceRegistry` in the container as an instance binding inside the boot closure (`$container->instance(PreferenceRegistry::class, $app->preferenceRegistry)`). Marko does not bind it by default — the registry lives on the `Application` instance. The container must be told about it before `ConfigResolver::get()` or `PreferenceAwareScanner::expand()` can resolve it
- Boot closure ordering (MUST be in this order):
  1. Register `PreferenceRegistry` instance in the container
  2. Discover config classes via attribute scan (see "Config Class Discovery" below)
  3. Build the `ConfigRegistry` from discovered config classes
  4. Register `ProxyAutoloader` so generated proxies become loadable
  5. **In dev mode only**: stale-proxy regeneration (see "Dev-mode codegen" below)
  6. (NO secret-cipher boot validation — validation is lazy, on first encrypt/decrypt call)
- **Dev-mode codegen** (decided in plan refinement):
  - Production must NEVER regenerate at boot — CI runs `config:generate` post-deploy and that output is authoritative
  - Dev mode (read from `ConfigRepositoryInterface` — flag `markommerce.config.auto_regenerate`, defaults true when `app.env === 'dev'`, false otherwise) runs a stale check during boot:
    - For each registered config class, find the class's source file (via `(new ReflectionClass(...))->getFileName()`)
    - Find the expected proxy file path via `ProxyLocator` + the target dir
    - If proxy file is missing OR config-class source mtime > proxy mtime, invoke `ProxyGenerator::generate()` + `ProxyWriter::write()` for that ONE class (not the whole registry)
    - Log a one-line notice per regenerated proxy at debug level
  - Stale check is fast (one `filemtime()` per registered config class). Tolerable per-request in dev; never runs in prod.
  - Failure mode: if regeneration throws (e.g., `InvalidConfigClassException`), boot fails with the original exception — dev needs to see the codegen error loudly. CI/prod is unaffected because regen doesn't run there.
- Bind `ConfigResolver` such that the `CachingConfigResolver` decorator wraps the base resolver — i.e., consumers who type-hint `ConfigResolver` receive the caching wrapper (caching is on by default; opt out by depending on `BareConfigResolver` if a future need arises — not in v1)
- **Config Class Discovery** (CRITICAL — replaces the failed "marko/config node" approach from earlier drafts):
  - Marko's `ConfigMerger` *replaces* numeric/list arrays on merge, so a shared list under `markommerce.config.classes` cannot compose across modules
  - Instead, build a `ConfigClassDiscovery` service modeled on `Marko\Core\Command\CommandDiscovery` / `Marko\Core\Container\PreferenceDiscovery`: scan each `ModuleManifest`'s `src/` directory for classes that have any property carrying `#[Config]`. Use `ClassFileParser` from `marko/core` (the same parser used by command/preference discovery) for the cheap file pre-filter (look for the substring `#[Config(` before paying for reflection)
  - Discovery runs in the boot closure; result is the input list to `ConfigRegistryBuilder::build()`
  - `ModuleRepositoryInterface` (already bound by `marko/core`) provides the manifest list
- `globalMiddleware`: register `ConfigCacheResetMiddleware` at priority lower than scope's middleware (priority < 5) so the cache is fresh before any scope-aware request handling
- Commands are auto-discovered by Marko via the `#[Command]` attribute (see `Marko\Core\Command\CommandDiscovery`); no explicit registration in `module.php` is required

## Requirements (Test Descriptions)
- [ ] `it binds ConfigStorageInterface to InMemoryConfigStorage by default`
- [ ] `it registers PreferenceRegistry as an instance in the container during boot`
- [ ] `it discovers config classes by scanning module src directories for properties with #[Config]`
- [ ] `it builds the ConfigRegistry at boot from the discovered class list`
- [ ] `it registers the ProxyAutoloader at boot so generated proxies resolve`
- [ ] `it does NOT instantiate SecretCipher at boot — only on first encrypt/decrypt invocation`
- [ ] `it throws a loud setup exception at the FIRST secret read/write when MARKOMMERCE_CONFIG_SECRET_KEY is unset`
- [ ] `it verifies the five #[Command]-annotated command classes exist under src/Command/ (Marko auto-discovers them)`
- [ ] `it registers ConfigCacheResetMiddleware in globalMiddleware so RequestConfigCache is cleared per request`
- [ ] `it binds SecretCipherInterface lazily via a closure that reads the 32-byte key from ConfigRepositoryInterface on first call`
- [ ] `it regenerates a stale proxy at boot when markommerce.config.auto_regenerate is true and the config class source is newer than the proxy file`
- [ ] `it does NOT regenerate any proxy at boot when markommerce.config.auto_regenerate is false`
- [ ] `it generates a missing proxy at boot when markommerce.config.auto_regenerate is true and the proxy file is absent`
- [ ] `it bubbles up InvalidConfigClassException from dev-mode regeneration so boot fails loudly`
- [ ] `it binds ConfigResolver to the CachingConfigResolver decorator so all consumers get caching by default`

## Acceptance Criteria
- `module.php` returns an array matching scope's shape (`bindings`, `singletons`, `boot`, `globalMiddleware`)
- The module is automatically picked up by Marko's module discovery (no extra registration steps)
- Boot is idempotent — calling it twice does not double-register the autoloader, does not double-instance `PreferenceRegistry`
- Marko's `DependencyResolver` ensures `markommerce/scope` boots before `markommerce/config` (composer dep) — integration test asserts that `ConfigRegistry` build succeeds when `ScopeRegistry` is fully wired
- PHPStan level 8 clean
- A small integration test wires the module into a test container and verifies the full chain: declare a fixture `CatalogConfig` test class under a temp module path, run discovery, build registry, generate its proxy on disk, resolve it via `ConfigResolver::get(...)`, read a property

## Implementation Notes
(Left blank — filled in by programmer)
