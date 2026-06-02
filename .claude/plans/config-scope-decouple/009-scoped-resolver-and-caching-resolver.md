# Task 009: Implement ScopedConfigResolver and ScopedCachingConfigResolver via #[Preference]

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Add `Markommerce\\ConfigScope\\ScopedConfigResolver` extending `Markommerce\\Config\\ConfigResolver`, carrying `#[Preference(replaces: ConfigResolver::class)]`. Its constructor adds `ScopedConfigStorageInterface`, `OverrideMatcher`, `ScopeContext`, and `ScopedFieldRegistry` on top of the parent's dependencies. Override `resolved($class, $field)` and add `resolvedAt($class, $field, ScopeContext)`: the resolver consults `ScopedFieldRegistry::axesForProperty(...)` per call, loads overrides via `ScopedConfigStorageInterface::loadOverrides(key)`, calls `OverrideMatcher::match(...)`, and falls back to the parent (global-or-default) when no override matches. Add `Markommerce\\ConfigScope\\Cache\\ScopedCachingConfigResolver` extending `Markommerce\\Config\\Cache\\CachingConfigResolver`, carrying `#[Preference(replaces: CachingConfigResolver::class)]`. Its `buildCacheKey()` reads axes from `ScopedFieldRegistry` and includes the active `ScopeContext` values in the key (overrides parent's `protected buildCacheKey()` from task 002).

**Module-level factory binding (critical):** the Preference alone cannot preserve Tier 1's caching wrap because the Container auto-wires the swapped class and bypasses the Tier 1 factory bound on `ConfigResolver::class`. `config-scope/module.php` MUST therefore register its own binding:

```php
'bindings' => [
    \Markommerce\Config\ConfigResolver::class => static function (ContainerInterface $c): ScopedCachingConfigResolver {
        $base = new ScopedConfigResolver(
            // … parent deps from container + scoped extras
        );
        return new ScopedCachingConfigResolver(
            $base,
            $c->get(ConfigCacheInterface::class),
            $c->get(ConfigRegistry::class),
            $c->get(ScopeContext::class),
            $c->get(ScopedFieldRegistry::class),
        );
    },
],
```

This binding takes precedence over the Tier 1 closure because module-merging applies later bindings last. The `#[Preference]` attribute on `ScopedConfigResolver` remains useful for downstream consumers that constructor-inject `ConfigResolver` directly (auto-wired path).

## Context
- Related files (new):
  - `packages/config-scope/src/ScopedConfigResolver.php`
  - `packages/config-scope/src/Cache/ScopedCachingConfigResolver.php`
  - `packages/config-scope/tests/Unit/ScopedConfigResolverTest.php`
  - `packages/config-scope/tests/Unit/Cache/ScopedCachingConfigResolverTest.php`
- Related (read-only):
  - `packages/config/src/ConfigResolver.php` (parent)
  - `packages/config/src/Cache/CachingConfigResolver.php` (parent)
  - `packages/scope/src/Metadata/ScopedFieldRegistry.php`
  - `packages/scope/src/Context/ScopeContext.php`
- Patterns to follow: P2's `ScopedProductGridComponent` shape — `extends` the parent, `#[Preference(replaces: …)]` attribute, constructor delegates to parent for shared deps and stores its own extras as private. The Preference auto-discovery scans `src/` so the attribute on the class is sufficient.

## Requirements (Test Descriptions)
- [ ] `it carries the #[Preference(replaces: ConfigResolver::class)] attribute on ScopedConfigResolver`
- [ ] `it extends Markommerce\Config\ConfigResolver`
- [ ] `it returns the property default value from ScopedConfigResolver resolved when no axes are registered for the field (delegates to parent)`
- [ ] `it returns the global value from ScopedConfigResolver resolved when axes are registered but no overrides exist`
- [ ] `it returns the matching override value from ScopedConfigResolver resolved when an override matches the current ScopeContext`
- [ ] `it falls back from override to global when no override matches the current ScopeContext`
- [ ] `it casts the override value to the declared type via ValueCaster`
- [ ] `it decrypts secret override values via SecretCipher before casting`
- [ ] `it resolves under an explicit ScopeContext passed to resolvedAt without reading the injected live context`
- [ ] `it does not mutate the injected ScopeContext when resolvedAt is called`
- [ ] `it carries the #[Preference(replaces: CachingConfigResolver::class)] attribute on ScopedCachingConfigResolver`
- [ ] `it extends Markommerce\Config\Cache\CachingConfigResolver`
- [ ] `it builds a cache key including active axes from ScopeContext when ScopedCachingConfigResolver resolved is called for a scoped field`
- [ ] `it builds a cache key with no axes suffix when the field is not registered in ScopedFieldRegistry`
- [ ] `it caches resolution results per (configKey, axis context) pair, so changing the ScopeContext returns a freshly-resolved value`
- [ ] `it binds ConfigResolver::class in config-scope/module.php to a closure that returns a ScopedCachingConfigResolver wrapping a ScopedConfigResolver, so container::get(ConfigResolver::class) returns the cached scoped resolver (preserves Tier 1's caching wrap)`
- [ ] `it returns the ScopedCachingConfigResolver from container::get(ConfigResolver::class) when all module manifests are booted and PreferenceRegistry is wired (verifies the binding wins over the Tier 1 factory + Preference autowiring path)`

## Acceptance Criteria
- All requirements have passing tests.
- `BootContributionTest`-style validation in this task (or task 013's E2E) asserts the container, when given a manifest with `path` set, returns `ScopedConfigResolver` from `$container->get(ConfigResolver::class)` after Preference discovery runs.
- PHPStan level 8 clean for new files.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
