# Task 014: `ProxyGenerator` + `ProxyWriter`

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Generate the typed-proxy PHP source for every config class in the registry. Each proxy is a subclass of the original config class that overrides every `#[Config]` property with a PHP 8.4 property hook delegating to `ConfigResolver::resolved($originalClass, $field)`. `ProxyWriter` is the file-system half: it lays out the generated files under `var/generated/config-proxies/` mirroring the original namespace.

## Context
- Generator input: a `ConfigDefinition` (or a list of them grouped by configClass) → PHP source as a string
- Each property gets a property hook of the form:
  ```php
  public string $welcomeMessage {
      get => $this->__resolver->resolved(
          \Markommerce\Catalog\Config\CatalogConfig::class,
          'welcomeMessage',
      );
  }
  ```
- The generated class declares a constructor taking `ConfigResolver $__resolver` (single dependency)
- Property type comes from `ConfigDefinition::$type` (already normalized in task 005)
- For unscoped configs (empty axes), the hook still delegates to `resolved()` — the resolver still consults storage for a global value
- ProxyWriter receives the source + the desired FQN (e.g., `Markommerce\Config\Generated\Markommerce\Catalog\Config\CatalogConfig_Resolved`) and writes it to `<targetDir>/<path-mirroring-namespace>.php`
- Target directory is injected (default `var/generated/config-proxies/` relative to project root, overridable for tests). The root `.gitignore` already lists `var/` so generated proxies are covered — NO additional gitignore changes needed
- Property hooks are a first-class PHP 8.4 feature — NOT magic methods — and comply with the project's "No magic methods" rule
- ProxyGenerator validates each property: reject `readonly` properties, union/intersection types, properties without explicit type — throw `InvalidConfigClassException` (task 002) with the specific reason
- ProxyGenerator also validates the **class shape**: the target config class must NOT declare a constructor with required parameters. Codegen's subclass constructor takes a single `ConfigResolver $__resolver` argument; if the parent has required ctor args, the generated subclass would either need to call `parent::__construct(...)` with unknown values or violate constructor contract. Throw `InvalidConfigClassException::configClassHasRequiredConstructor($configClass)` with the suggestion "config classes are DTO-shaped — make all constructor args optional or remove the constructor entirely"
- The subclass's redeclared property type MUST be IDENTICAL to the parent's — PHP 8.4 does not allow covariant property type widening / narrowing. The generator copies the type string verbatim from `ConfigDefinition::$type`
- For enum-typed properties, the type string is the FQN (e.g., `\App\Color`); generator emits the leading backslash to avoid namespace ambiguity in the generated file

## Requirements (Test Descriptions)
- [x] `it generates a subclass extending the original config class`
- [x] `it generates a property hook get clause for every #[Config] property`
- [x] `it preserves the original property's declared type in the override`
- [x] `it injects ConfigResolver as the only constructor dependency named __resolver`
- [x] `it writes the source to a file path mirroring the original namespace under the target directory`
- [x] `it creates intermediate directories when the target path nests`
- [x] `it throws InvalidConfigClassException when a property is readonly`
- [x] `it throws InvalidConfigClassException when a property has a union type`
- [x] `it throws InvalidConfigClassException when the config class declares a constructor with required parameters`
- [x] `it emits enum-typed property hooks using a leading-backslash FQN for the enum type`
- [x] `it produces source that PHP can parse and require (require + class_exists assertion in a unique-class-per-test fixture)`

## Acceptance Criteria
- `ProxyGenerator::generate(string $configClass, list<ConfigDefinition> $definitions): string` returns valid PHP source
- `ProxyWriter::write(string $generatedFqn, string $source, string $targetDir): string` writes the file and returns the path
- Generated source uses PHP 8.4 property hooks, NOT magic `__get`
- Generated namespace pattern: `Markommerce\Config\Generated\<OriginalNamespace>\<Class>_Resolved`
- Tests parse generated source via PHP's tokenizer and assert structure (no shelling out)
- PHPStan level 8 clean (note: the generated *output* doesn't have to be PHPStan-clean for this task — that's verified in task 016 integration)

## Implementation Notes
(Left blank — filled in by programmer)
