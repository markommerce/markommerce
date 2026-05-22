# Task 004: Core Value Objects

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the core value-object types that make up a layout tree: `Layout`, `Place`, `Slot` (keyed and repeat variants), `Provide`, and the `LayoutDefinition` + `ContextProvider` contract interfaces. These are pure, immutable, serializable data structures — no behavior beyond construction and simple accessors.

## Context
- A layout file `return`s a `new Layout(...)`. Shape (from `_plan.md`):
  - `Layout(array|string|null $handle, ?string $extends, array $context, array $slots, ?string $template = null)` — `handle` is `[ControllerClass, 'action']` for a routable layout OR **null** for a handle-less base layout used only as an `extends:` target (task 018 relies on this — a base layout binds to no route); `extends` is a `class-string<LayoutDefinition>`; `context` is a list of `Provide`; `slots` is `array<string, list<Place>>` mixed with `Slot` repeat objects; `template` is the Latte template name a base/root layout renders into (the old `marko/layout` `#[Component(template:...)]` value moves here — the renderer in task 013 needs it). A routable layout that `extends:` a base may leave `template` null and inherit the base's.
  - `Place(string $component, ?string $name, array $props, array $slots)` — `component` is a `class-string`; `props` is `array<string, Source|scalar|array>`; `slots` follows the same shape as `Layout::$slots`.
  - `Slot::repeat(string $dataKey, string $yields, string $as, array $children)` — named constructor producing a repeat slot; `yields` is the item `class-string`, `as` is the iteration-token `class-string`.
  - `Provide(string $token, string $provider, array $props)` — `token` is a marker `class-string`, `provider` is a `class-string<ContextProvider>`.
- `LayoutDefinition` interface: `public static function define(): Layout;` — `extends:` targets a class implementing this.
- `ContextProvider` interface: a single method returning the token's value, receiving resolved props (mirror how components receive props). Define a clear signature, e.g. `public function provide(...): object`.
- All value objects are `readonly class`, no `final`.
- `Slot` must distinguish keyed vs repeat — model with a `kind` enum or two clearly separated constructors. A keyed slot is just a `list<Place>` under a string key in `Layout::$slots`; a repeat slot is a `Slot` object. Decide and document the representation in Implementation Notes.
- Patterns to follow: `marko/layout/src/ComponentDefinition.php` (value-object style), `code-standards.md`.

## Requirements (Test Descriptions)
- [x] `it constructs a Layout with a handle, extends target, context list and slots`
- [x] `it accepts a controller-action pair as a layout handle`
- [x] `it allows a Layout with a null handle for a base layout used only via extends`
- [x] `it carries an optional template name on a Layout`
- [x] `it constructs a Place with component, name, props and sub-slots`
- [x] `it allows a Place with a null name`
- [x] `it constructs a keyed slot as a list of placements`
- [x] `it constructs a repeat slot via Slot::repeat with data key, yields type and iteration token`
- [x] `it exposes the yields type and iteration token on a repeat slot`
- [x] `it constructs a Provide with a token class, provider class and props`
- [x] `it defines a LayoutDefinition interface with a static define method`
- [x] `it defines a ContextProvider interface`

## Acceptance Criteria
- All requirements have passing tests
- All value objects are `readonly class`, none `final`
- `LayoutDefinition` and `ContextProvider` live in `src/Contracts/`
- Representation of keyed vs repeat slots is documented in Implementation Notes

## Implementation Notes

### Keyed vs repeat slot representation

`Layout::$slots` (and `Place::$slots`) are typed as `array<string, list<Place>|Slot>`:

- **Keyed slot**: stored as `list<Place>` directly under its string key. No wrapper object.
- **Repeat slot**: stored as a `Slot` object under its string key. Created via the `Slot::repeat()` named constructor. The `Slot` class has a private constructor to enforce use of the factory method.

This means consumers can `instanceof` check the value to determine which variant they are dealing with: `$value instanceof Slot` → repeat; otherwise → keyed list.

### ContextProvider signature

`public function provide(array $props): object;` — receives resolved props as a plain array and returns the context token value as an object.
