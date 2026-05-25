# Task 016: `ConfigResolver::get()` typed entry point

**Status**: completed
**Depends on**: 010, 015
**Retry count**: 0

## Description
Add the typed read entry point: `ConfigResolver::get(string $configClass): object`. It resolves the (potentially preferenced) effective class, looks up the corresponding generated proxy class via `ProxyLocator`, instantiates the proxy with `$this` as the resolver dependency, and returns it. PHPDoc generics make the return statically typed as the original class.

**Important**: task 010 already declared `ProxyLocator` and `PreferenceRegistry` as constructor dependencies on `ConfigResolver` (with no-op stubs). This task only adds the new `get()` method — **no constructor changes**. The `module.php` wiring (task 020) is what swaps the no-op `ProxyLocator` for the real one populated from task 015.

## Context
- Method shape:
  ```php
  /**
   * @template T of object
   * @param class-string<T> $configClass
   * @return T
   * @throws ProxyNotGeneratedException
   * @throws InvalidConfigClassException  // when preferred class is not a subclass of $configClass
   */
  public function get(string $configClass): object
  {
      $effective = $this->preferenceRegistry->getPreference($configClass) ?? $configClass;
      if ($effective !== $configClass && !is_subclass_of($effective, $configClass)) {
          throw InvalidConfigClassException::nonSubclassPreference($configClass, $effective);
      }
      $proxyClass = $this->proxyLocator->proxyClassFor($effective);
      if (!class_exists($proxyClass)) {
          throw ProxyNotGeneratedException::forClass($effective, $proxyClass);
      }
      return new $proxyClass($this);
  }
  ```
- `PreferenceRegistry::getPreference()` returns `?string` and follows chains internally (cycle detection is built in — it throws `PreferenceConflictException::circularPreference()` on cycles). No `PreferenceResolverInterface` adapter is needed; depend on Marko's `Marko\Core\Container\PreferenceRegistry` directly. Task 020 binds it as an instance.
- For tests: write a small hand-authored proxy class inside `tests/Unit/Resolver/Fixtures/` that mirrors what `ProxyGenerator` would produce. This decouples the test from the codegen task while still exercising the runtime path.
- Tests pass a real `PreferenceRegistry` instance (it has no constructor deps) populated via `register(original, replacement, …)` calls.

## Requirements (Test Descriptions)
- [x] `it returns an instance of the generated proxy class for a non-preferenced config class`
- [x] `it returns an instance of the preferred proxy class when a Preference is registered via PreferenceRegistry`
- [x] `it instantiates the proxy with the resolver as the __resolver dependency`
- [x] `it throws ProxyNotGeneratedException with a config:generate suggestion when the proxy class is missing`
- [x] `it throws InvalidConfigClassException when the preferred class is not a subclass of the requested config class`
- [x] `it returns instances whose property hooks delegate through to ConfigResolver::resolved() for each field`

## Acceptance Criteria
- `ConfigResolver::get(string $configClass): object` works as specified
- PHPDoc `@template`/`@param class-string<T>`/`@return T` annotations present so PHPStan + IDE infer the return type as the original class
- All earlier `ConfigResolver` tests still pass (regression-free)
- Tests use a hand-written fixture proxy class
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer)
