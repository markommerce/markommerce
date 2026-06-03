# Task 002: Strip scope from ConfigResolver, CachingConfigResolver, and ConfigRegistryBuilder

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove `ScopeContext`, `OverrideMatcher`, and the `resolvedAt(...)` method from `ConfigResolver`. Drop `ScopeContext` injection and axes-aware cache key construction from `CachingConfigResolver`. Drop `ScopeRegistryInterface` parameter and `#[Scoped]` attribute handling from `ConfigRegistryBuilder`. After this task, `ConfigResolver::resolved(class, field)` returns `row->value` or `definition->defaultValue` with no scope-context awareness, and `ConfigRegistryBuilder::build($configClasses)` is a single-argument call.

**Critical visibility prep for Tier 2 subclassing:** relax the constructor-promoted property visibility on `ConfigResolver` (`$configRegistry`, `$configStorage`, `$valueCaster`, `$secretCipher`, `$proxyLocator`, `$preferenceRegistry`) and `CachingConfigResolver` (`$configResolver`, `$configCache`, `$configRegistry`) from `private` to `protected`. Also relax `CachingConfigResolver::buildCacheKey()` from `private` to `protected`. Task 009's `ScopedConfigResolver` and `ScopedCachingConfigResolver` cannot otherwise read parent state or override the cache-key builder.

## Context
- Related files:
  - `packages/config/src/ConfigResolver.php`
  - `packages/config/src/Cache/CachingConfigResolver.php`
  - `packages/config/src/Registry/ConfigRegistryBuilder.php`
  - `packages/config/module.php` (the `ConfigResolver` factory closure and `ConfigRegistry` build path both need updates)
  - `packages/config/tests/Unit/ConfigResolverTest.php`
  - `packages/config/tests/Unit/Cache/CachingConfigResolverTest.php`
  - `packages/config/tests/Unit/Registry/ConfigRegistryBuilderTest.php`
  - `packages/config/tests/Unit/Resolver/ConfigResolverGetTest.php`
- Patterns to follow: P2's `ProductGridComponent` simplification — drop the scope dep, retain the same public method name but with a simpler body.
- All scope-related test cases (e.g. `returns the override value when a matching signature exists`, `falls back from override to global`, `preserves most-specific override priority`, `resolves under a synthetic ScopeContext via resolvedAt`, `captures axes from #[Scoped]`) are deleted from the listed test files — they relocate to config-scope in tasks 008–009.

## Requirements (Test Descriptions)
- [ ] `it constructs ConfigResolver with seven dependencies: registry, storage, valueCaster, secretCipher, proxyLocator, preferenceRegistry — no ScopeContext, no OverrideMatcher`
- [ ] `it does not expose a resolvedAt method on the ConfigResolver class after task completes`
- [ ] `it returns the property default value when ConfigResolver resolved is called and no row exists for the key`
- [ ] `it returns the row global value cast to the declared type when ConfigResolver resolved finds a row with a non-null value`
- [ ] `it returns the property default value when ConfigResolver resolved finds a row with value=null`
- [ ] `it throws ConfigNotFoundException when ConfigResolver resolved is called for a class/field pair not in the registry`
- [ ] `it constructs CachingConfigResolver with three dependencies: configResolver, configCache, configRegistry — no ScopeContext`
- [ ] `it caches resolution results keyed solely by the configKey when CachingConfigResolver resolved is called`
- [ ] `it constructs ConfigRegistryBuilder with no constructor parameters and exposes a single-argument build(configClasses) method`
- [ ] `it builds a ConfigRegistry from a list of #[Config]-annotated classes without consulting any scope registry`
- [ ] `it ignores any #[Scoped] attributes on properties when building the registry (no axes captured)`
- [ ] `it throws ConfigKeyConflictException unchanged when two properties declare the same #[Config(key)]`
- [ ] `it throws InvalidConfigClassException unchanged when a #[Config] property has a union type`
- [ ] `it does not import Markommerce\Scope\... namespaces from any of the three production files after task completes`
- [ ] `it declares ConfigResolver's constructor-promoted properties (configRegistry, configStorage, valueCaster, secretCipher, proxyLocator, preferenceRegistry) with protected visibility (verified via reflection)`
- [ ] `it declares CachingConfigResolver's constructor-promoted properties (configResolver, configCache, configRegistry) with protected visibility (verified via reflection)`
- [ ] `it declares CachingConfigResolver::buildCacheKey() with protected visibility so subclasses can override it (verified via reflection)`

## Acceptance Criteria
- All requirements have passing tests.
- `grep -rn "Markommerce\\Scope" packages/config/src/ConfigResolver.php packages/config/src/Cache/CachingConfigResolver.php packages/config/src/Registry/ConfigRegistryBuilder.php` returns zero matches.
- `module.php`'s `ConfigResolver` factory closure no longer references `ScopeContext`; the `ConfigRegistry` build step no longer passes a `ScopeRegistryInterface` argument.
- PHPStan level 8 clean for all touched files.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
