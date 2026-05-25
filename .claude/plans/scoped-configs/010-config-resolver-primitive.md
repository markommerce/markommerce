# Task 010: `ConfigResolver::resolved()` primitive

**Status**: completed
**Depends on**: 005, 006, 007, 008, 012
**Retry count**: 0

## Description
Implement the resolver primitive that reads one config value given a class FQN + field name, observing the current `ScopeContext`. Resolution priority: per-scope override (via `OverrideMatcher`) → row's global `value` → definition's `defaultValue`. Returns the value cast to its declared PHP type via `ValueCaster`. This is the layer the generated typed proxies will call into (task 016 wires that up).

## Context
- Class shape — declares ALL final dependencies upfront so tasks 013 and 016 do NOT need to alter the constructor signature later (they only swap stub bindings for real impls in `module.php`):
  ```php
  class ConfigResolver
  {
      public function __construct(
          private ConfigRegistry $configRegistry,
          private ConfigStorageInterface $configStorage,
          private OverrideMatcher $overrideMatcher,
          private ValueCaster $valueCaster,
          private ScopeContext $scopeContext,
          private SecretCipherInterface $secretCipher,       // task 013 fills behavior; default binding here = NullSecretCipher
          private ProxyLocator $proxyLocator,                 // task 016 introduces real one; tests use a no-op fixture
          private PreferenceRegistry $preferenceRegistry,     // Marko core type; tests pass an empty new PreferenceRegistry()
      ) {}

      public function resolved(string $configClass, string $field): mixed { ... }
      public function resolvedAt(string $configClass, string $field, ScopeContext $explicitContext): mixed { ... }
      // get() method added in task 016 — does not require constructor change
  }
  ```
- `resolvedAt()` is included in THIS task (not deferred). It runs the same resolution chain as `resolved()` but consults the explicitly-passed `ScopeContext` instead of the injected one. Required by the CLI (`config:get --scope=...`) so commands can read under a synthetic scope without mutating the live request-scoped `ScopeContext`. Treat `resolved()` as `resolvedAt($configClass, $field, $this->scopeContext)`.
- For this task, `secretCipher`, `proxyLocator`, and `preferenceRegistry` are accepted but not yet exercised. The `NullSecretCipher` stub (added to task 012 as a fallback) throws `SecretCipherException::notConfigured()` on encrypt/decrypt — but is never called when no secret config is read.
- Tests for this task may pass any of these as null-object stubs (NullSecretCipher, a hand-written empty ProxyLocator, a fresh PreferenceRegistry). The point: future tasks DO NOT touch constructor wiring, only the bindings in `module.php`.
- Lookup chain: `definition = registry->definition($configClass, $field)`; `row = storage->load($definition->key)`; if row is null → cast `definition->defaultValue`; else try `overrideMatcher->match($row, $definition->axes, $scopeContext)`; if non-null → cast; else if `row->value !== null` → cast that; else fall back to `definition->defaultValue` (NOT cast — defaults are already typed).
- Defaults are pre-typed because they come from the declared PHP property — no casting needed on the default path.
- Secrets decryption is NOT in this task — task 013 layers the `SecretCipher` decrypt step between the storage read and the cast (but the constructor already has the dependency).

## Requirements (Test Descriptions)
- [x] `it returns the property default when no row exists for the config key`
- [x] `it returns the global value cast to the declared type when the row has a global but no matching overrides`
- [x] `it returns the override value when a matching signature exists in the row for the current ScopeContext`
- [x] `it falls back from override to global when no override matches the current ScopeContext`
- [x] `it falls back from global to default when the global is null and no override matches`
- [x] `it preserves the most-specific override priority via OverrideMatcher when both single-axis and composite overrides are present`
- [x] `it casts stored ints to int and stored strings to string`
- [x] `it propagates InvalidConfigValueException unchanged from ValueCaster`
- [x] `it throws ConfigNotFoundException when the configClass + field pair is not in the ConfigRegistry`
- [x] `it resolves under a synthetic ScopeContext passed to resolvedAt without reading from the injected live context`
- [x] `it does not mutate the injected ScopeContext when resolvedAt is called`

## Acceptance Criteria
- `ConfigResolver::resolved(string $configClass, string $field): mixed` returns correctly-typed values per the resolution chain
- `ConfigResolver::resolvedAt(string $configClass, string $field, ScopeContext $explicitContext): mixed` provided in this task
- Constructor accepts all eight final dependencies upfront (see Context); tasks 013 and 016 add behavior without touching the constructor
- Tests cover all five fallback paths above
- Uses fakes: `InMemoryConfigStorage`, `FakeScopeContext` (with a registered axis registry), `NullSecretCipher` (added in task 012), a no-op `ProxyLocator` fixture, an empty `PreferenceRegistry` from `marko/core`
- PHPStan level 8 clean
- `@throws ConfigNotFoundException|InvalidConfigValueException` documented

## Implementation Notes
(Left blank — filled in by programmer)
