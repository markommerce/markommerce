# Task 005: Build ScopeResolverChainFactory

**Status**: complete
**Depends on**: 002, 004
**Retry count**: 0

## Description
Builds the list of `ScopeAxisResolverInterface` instances for a given axis from its config block. Accepts both forms: bare class-string (`CookieResolver::class`) and array (`['class' => CookieResolver::class, 'cookieName' => 'site_locale']`). Validates the config at build time and caches built chains keyed by axis name.

## Context
- Target file: `packages/scope/src/Resolver/Resolution/ScopeResolverChainFactory.php`
- Constructor deps: `Marko\Core\Container\ContainerInterface $container`, `Marko\Config\ConfigRepositoryInterface $configRepository`.
- **Important:** `ConfigRepositoryInterface::get(string $key, ?string $scope = null): mixed` — the second arg is `$scope`, NOT a default value. It THROWS `Marko\Config\Exceptions\ConfigNotFoundException` when the key is missing. Do NOT pass `[]` as second arg (type error).
- Use this pattern instead:
  ```php
  $configKey = "scope.axes.{$axisName}.resolvers";
  if (!$this->configRepository->has($configKey)) {
      return [];
  }
  $entries = $this->configRepository->getArray($configKey);
  if ($entries === []) {
      return [];
  }
  ```
- For each entry:
  - Bare class-string: `$container->get($class)` — container resolves any constructor deps. Validate `$class instanceof ScopeAxisResolverInterface` after construction.
  - Array form: extract `class` key, pass remaining keys as named parameters to a manual `new $class(...$extraArgs)` construction. (Marko's container doesn't have a clean way to mix named-arg overrides with container resolution, so for parameterized resolvers, the array form takes full ownership of construction. Resolvers needing extra deps via container should use a constructor that takes both the params and the deps; container injection is not available for array-form entries.)
- Cache built chains per-axis on the factory instance (it's a singleton). Subsequent `for($axisName)` calls return the same array.
- All config validation failures throw `InvalidResolverConfigException` (task 004) — wrap the throw site in `try/catch` only at the pipeline level (task 006), NOT here. Here, throw immediately.

## Requirements (Test Descriptions)

- [x] `it returns empty chain when axis config has no resolvers key`
- [x] `it returns empty chain when axis resolvers key is empty array`
- [x] `it builds resolver from bare class-string entry`
- [x] `it builds resolver from array entry with class key and named arguments`
- [x] `it caches built chains so for is idempotent per axis`
- [x] `it throws InvalidResolverConfigException unknownClass when class string does not exist`
- [x] `it throws InvalidResolverConfigException missingClassKey when array entry lacks a class key`
- [x] `it throws InvalidResolverConfigException notImplementingInterface when class does not implement ScopeAxisResolverInterface`
- [x] `it preserves resolver order from the config array`

## Acceptance Criteria
- All requirements have passing tests
- Class is NOT `readonly` (it has a mutable cache property)
- Class is NOT `final` (project standard)
- All `@throws` tags present on public methods
- Tests use fakes implementing `ScopeAxisResolverInterface` — do not depend on real built-in resolvers (those are different tasks)
- Tests use a fake `ContainerInterface` and `ConfigRepositoryInterface` — see existing scope tests for the pattern

## Implementation Notes
- Implemented `ScopeResolverChainFactory` at `packages/scope/src/Resolver/Resolution/ScopeResolverChainFactory.php`
- Uses `configRepository->has()` guard before `getArray()` to avoid `ConfigNotFoundException`
- Bare class-string entries: resolved via `ContainerInterface::get()`, then validated as `ScopeAxisResolverInterface`
- Array entries: `class` key extracted, remaining keys spread as named args via `new $class(...$extraArgs)`
- Per-axis cache using a private `array $cache` property (class is NOT readonly)
- All 9 tests pass; pre-existing failures in other test files (SubdomainResolver, AcceptLanguageResolver) are unrelated to this task
