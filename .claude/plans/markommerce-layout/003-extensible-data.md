# Task 003: Extensible Data Primitives

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the primitives that let third-party modules add typed fields to a component's data DTO without subclassing it: `ExtensionAttribute` (marker interface), `ExtensionBag` (typed collection of extensions keyed by class), and `ExtensibleData` (abstract base every component data DTO extends). Also verify, with a concrete test, that a Marko `#[Plugin]` on a component's `data()` method can return a `withExtension(...)`-augmented DTO.

## Context
- Use case: `markommerce/catalog` defines `ProductCardData(id, name, sku, description)`. A `markommerce/reviews` module wants to add review stars. It must NOT subclass `ProductCardData`. Instead it defines a `ReviewStarsExtension implements ExtensionAttribute` and a `#[Plugin]` on `ProductCard::data()` that returns `$data->withExtension(new ReviewStarsExtension(...))`.
- `ExtensionAttribute` is a marker interface in `src/Contracts/`.
- `ExtensionBag` stores extensions keyed by class-string; `get(class-string<T>): ?T` is generically typed for PHPStan; `with(ExtensionAttribute): self` returns a new bag (immutable).
- `ExtensibleData` is `abstract readonly class` with a `public ExtensionBag $extensions` and a `withExtension(ExtensionAttribute): static` method using PHP 8.5 `clone(obj, [...])`.
- IMPORTANT — how the Marko Plugin mechanism actually works (verified against `marko/core/Plugin/`):
  - A `#[Plugin(target: SomeComponent::class)]` class declares interceptors. To mutate a method's **return value** the plugin method must be an **after** plugin: its signature is `methodName(mixed $result, ...$originalArgs): mixed` — it receives the original return value as the first argument and returns the (possibly replaced) value. A `before` plugin only rewrites arguments or short-circuits; it cannot post-process the DTO. The extension story therefore REQUIRES an after-plugin on `data()`.
  - Marko intercepts by generating a proxy. For a component with **no interface**, it uses the *concrete subclass* strategy (`PluginInterceptor::generateConcreteSubclass`). That strategy **throws for `readonly` classes** (a subclass cannot extend a readonly class with new state). Therefore component classes that expose a plugin-extensible `data()` MUST NOT be `readonly class` — document this constraint loudly; it affects task 017's `ProductCard`/`ProductGridComponent` and any third-party component.
  - Interception only applies when the component is resolved **through the DI container** (`$container->get(Component::class)`), not via `new`. The renderer (task 013) must instantiate components through the container or plugins silently no-op.
- This task must include a Feature test proving the after-plugin-on-`data()` flow works against Marko's actual Plugin mechanism, resolving the component through a real container so interception is exercised. If Marko Plugins cannot mutate a typed return value this way, that is a blocking finding to surface — note it in Implementation Notes.
- Patterns to follow: `markommerce/scope` value objects, `code-standards.md` readonly-class rule, `marko/core/tests/Feature/Plugin/PluginInterceptionIntegrationTest.php` for the real plugin flow.

## Requirements (Test Descriptions)
- [ ] `it stores an extension and retrieves it by class`
- [ ] `it returns null when an extension class is not present in the bag`
- [ ] `it returns a new bag when an extension is added preserving immutability`
- [ ] `it rejects two extensions of the same class in one bag`
- [ ] `it exposes an empty extension bag by default on ExtensibleData`
- [ ] `it returns a new data object with the extension when withExtension is called`
- [ ] `it keeps original core fields intact after withExtension`
- [ ] `it applies an extension to a component data DTO via a Marko after-plugin on the data method`
- [ ] `it preserves plugin interception when the component is resolved through the container`

## Acceptance Criteria
- All requirements have passing tests
- `ExtensionBag::get()` is generically typed so PHPStan level 8 narrows the return
- `ExtensibleData` is `abstract readonly class`
- The Plugin-flow Feature test uses an after-plugin and resolves the component through a real DI container
- The `readonly`-class / container-resolution constraints on plugin-extensible components are documented in Implementation Notes for tasks 013 and 017

## Implementation Notes
(Left blank - filled in by programmer during implementation)
