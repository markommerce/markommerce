# Task 006: Iteration Token Attribute & Decorator Interface

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create the `#[IteratesOver]` attribute that an iteration-token class carries to declare the type of item it yields, and the `DecoratorInterface` that a `WrapWith` decorator class implements. These are the two small contracts that the compiler and renderer rely on for repeat slots and wrapping.

## Context
- Iteration token: an empty marker class (e.g. `class ProductIteration {}`) annotated `#[IteratesOver(Product::class)]`. The compiler reads this attribute to know a `Slot::repeat(as: ProductIteration::class)` yields `Product` items, and to type-check `Source::iterated(ProductIteration::class)` against component prop types.
- `#[IteratesOver]` is `#[Attribute(Attribute::TARGET_CLASS)]`, `readonly class`, holds one `class-string $itemType`.
- `DecoratorInterface` (in `src/Contracts/`): a decorator is a component-like class used by the `WrapWith` operation. It declares its own template (via a `template(): string` method or a class attribute — decide and document; do NOT reuse `marko/layout`'s `#[Component]`). Its template must contain a `{slot inner}` placeholder. The interface defines how the renderer hands the wrapped child's rendered HTML to the decorator. A decorator MAY add markup and MAY have its own `data()` but MUST NOT receive or rewrite the wrapped component's props.
- Decide the exact `DecoratorInterface` method signature so the renderer (task 013) can call it. Document the contract: input = inner HTML string (+ optional decorator's own resolved data), output = wrapped HTML string. Document in Implementation Notes.
- The `{slot inner}` placeholder check: the renderer cannot validate a decorator's template by reflection alone. The test `it requires a decorator template to contain a slot inner placeholder` should use a real fixture decorator + a real fixture template file and assert the renderer/compiler raises a loud error when the placeholder is absent. Decide WHEN this check runs (compile-time when a `WrapWith` is resolved, or render-time) and document — compile-time is preferred so the error surfaces in `layout:compile`.
- Patterns to follow: `marko/layout/src/Attributes/Component.php` for attribute style.

## Requirements (Test Descriptions)
- [x] `it defines an IteratesOver attribute targeting classes`
- [x] `it exposes the item type from an IteratesOver attribute`
- [x] `it reads the IteratesOver attribute from an annotated token class via reflection`
- [x] `it defines a DecoratorInterface contract`
- [x] `it requires a decorator template to contain a slot inner placeholder`

## Acceptance Criteria
- All requirements have passing tests
- `#[IteratesOver]` is a `readonly class` attribute targeting classes only
- `DecoratorInterface` lives in `src/Contracts/`
- The decorator wrap contract (inputs/outputs) is documented in Implementation Notes

## Implementation Notes

### DecoratorInterface contract

**Location**: `packages/layout/src/Contracts/DecoratorInterface.php`

**Method signatures**:
- `template(): string` — returns the decorator's template string. MUST contain the literal `{slot inner}` placeholder.
- `wrap(string $innerHtml, array $data = []): string` — takes the already-rendered inner HTML of the wrapped component and optional decorator-own resolved data, returns the fully wrapped HTML string.

**Wrap contract**:
- Input: `$innerHtml` (rendered HTML of the wrapped component), `$data` (optional key→value array of the decorator's own resolved context data)
- Output: the wrapped HTML string with `{slot inner}` replaced by `$innerHtml`
- The decorator MUST NOT receive or rewrite the wrapped component's props — it only receives the rendered output.

### {slot inner} validation

**Location**: `packages/layout/src/Compiler/DecoratorTemplateValidator.php`

**When**: Compile-time, when a `WrapWith` operation is resolved during `layout:compile`. The validator instantiates the decorator class, calls `template()`, and asserts the result contains `{slot inner}`. Throws `MissingSlotInnerException` if not found, which surfaces during compilation rather than at render-time.

### IteratesOver attribute

**Location**: `packages/layout/src/Attributes/IteratesOver.php`

`readonly class`, `#[Attribute(Attribute::TARGET_CLASS)]`, holds one `class-string $itemType`. The compiler reads this attribute from the token class referenced in `Slot::repeat(as: ...)` to know what item type the slot yields, enabling type-checking of `Source::iterated(...)` props against component declarations.
