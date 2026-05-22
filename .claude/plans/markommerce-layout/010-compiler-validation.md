# Task 010: Compiler — Validation Phase

**Status**: completed
**Depends on**: 002, 006, 009
**Retry count**: 0

## Description
Build the validation phase: take each resolved tree from task 009 and run the full set of structural and type checks, throwing the appropriate loud exception from the catalog (task 002) on any failure. A tree that passes validation is guaranteed renderable.

## Context
- Input: `array<handleKey, ResolvedLayout>` from task 009.
- Checks to perform (each maps to one exception from task 002):
  - **Placement name format** — every non-null `name` matches `^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$`.
  - **Name uniqueness** — no two placements in one resolved tree share a `name` → `DuplicateNameException`.
  - **Context references** — every `Source::context(X)` has a matching `Provide(X)` in the layout (or its extends chain) → else `UnknownContextException`.
  - **Iteration references** — every `Source::iterated(X)` sits inside a `Slot::repeat(as: X)` ancestor → else `UnknownIterationException`.
  - **Repeat-slot data key** — for `Slot::repeat($dataKey, ...)`, the parent placement's component `data()` return DTO must declare a property named `$dataKey` → else `MissingDataKeyException`.
  - **Repeat-slot item type** — the `data()` DTO property `$dataKey` must be typed as a list/iterable of the repeat slot's `yields` type (cross-checked against the `#[IteratesOver]` on the `as` token) → else `RepeatTypeMismatchException`.
  - **Prop ↔ source type** — each placement prop is matched to the component's `data()` parameter of the same name; the Source's resolved type must be assignable to the parameter type → else `TypeMismatchException`. Required params (no default) with no prop → `MissingPropException`.
  - **parentData scope** — a `Source::parentData(...)` prop on a placement that has no enclosing parent placement (i.e. a top-level placement directly under a layout slot) → `UnknownContextException` (or a dedicated check) — there is no parent DTO to read. The renderer (task 013) defines "parent" as the immediate enclosing placement; this check enforces that statically.
  - **parentData key existence** — for a `Source::parentData($key)`, the immediate parent placement's component `data()` DTO must declare a public property named `$key` → else `MissingDataKeyException` (reuse the same DTO-property reflection used for repeat-slot keys).
  - **Dangling anchors** — any wrap marker / recorded operation referencing a name absent from the final tree → `DanglingAnchorException`.
- **Type resolution strategy** (the hard part): a component's data shape comes from the return type of its `data()` method. Per the locked decision, `data()` returns a typed DTO (a class). Use reflection on the DTO's `public` properties to get field names + native types. Native PHP types cover scalars and class-typed properties — those need NO PHPDoc parsing. PHPDoc parsing is needed ONLY for collection element typing (a property typed `array`/`iterable` whose element type is in the docblock).
  - Supported `@var` forms (closed set — anything else is a validation error telling the author to use a supported form): `list<X>`, `array<int, X>`, `X[]`, where `X` is a fully-qualified or imported class name. Resolve `X` against the DTO file's `use` imports + namespace via reflection (`ReflectionProperty::getDeclaringClass()` + the class's namespace) — a bare unqualified name with no matching `use` is a loud error.
  - The `data()` method's return type MUST be a concrete DTO class (a single named type). If `data()` returns `array`, a union, or an untyped value, that is a `TypeMismatchException`-class loud error at compile — the typed-DTO decision is mandatory, not optional. State this explicitly.
  - Document the exact supported annotation forms AND the import-resolution approach in Implementation Notes.
- Every thrown exception must carry the placement chain in `context` (e.g. `category_show → content > catalog.product_grid > catalog.product_card`).
- Validation does not mutate the tree — it either passes or throws.
- Patterns to follow: task 002 exceptions, `code-standards.md`.

## Requirements (Test Descriptions)
- [x] `it passes a fully valid resolved tree without error`
- [x] `it throws DuplicateNameException when two placements share a name`
- [x] `it throws an error when a placement name violates the name format`
- [x] `it throws UnknownContextException when a context source has no matching Provide`
- [x] `it throws UnknownIterationException when an iterated source has no enclosing repeat slot`
- [x] `it throws MissingDataKeyException when a repeat slot key is absent from the parent data DTO`
- [x] `it throws RepeatTypeMismatchException when the repeat item type does not match the yields type`
- [x] `it throws TypeMismatchException when a source type is not assignable to the component prop`
- [x] `it throws MissingPropException when a required component prop has no source`
- [x] `it throws DanglingAnchorException when a wrap marker references a missing placement`
- [x] `it rejects a parentData source on a top-level placement with no parent`
- [x] `it throws MissingDataKeyException when a parentData key is absent from the parent data DTO`
- [x] `it rejects a component whose data method does not return a concrete DTO class`
- [x] `it includes the placement chain in the context of every validation error`

## Acceptance Criteria
- All requirements have passing tests
- Every check throws the correct named exception from the catalog
- No full PHPDoc-parser dependency added — only the documented minimal annotation forms supported
- Supported `@var` annotation forms documented in Implementation Notes

## Implementation Notes

### ValidationPhase service
`packages/layout/src/Compiler/ValidationPhase.php` — single `validate(array<string, ResolvedLayout>): void` method.

### Supported @var annotation forms for collection element typing
- `list<ClassName>` — e.g. `/** @var list<ProductItemDto> */`
- `array<int, ClassName>` — e.g. `/** @var array<int, ProductItemDto> */`
- `ClassName[]` — e.g. `/** @var ProductItemDto[] */`

Where `ClassName` may be:
- A fully-qualified class name starting with `\`
- A short name resolvable via `use` statements in the declaring class's file
- A short name that falls back to the same namespace as the declaring class

Import resolution: parse `use` statements from the declaring class's file using `file_get_contents` + regex on `ReflectionProperty::getDeclaringClass()->getFileName()`. Supports `use Foo\Bar as Alias` syntax.

### data() return type rule
A component's `data()` method MUST return a single named class type. If it returns `array`, a union type, or has no declared return type, `TypeMismatchException` is thrown at compile time.

### Exception factory methods added
Each exception class got a `*WithChain` factory method that includes the placement chain string in the `context` field. Chain format: `handleKey → name1 > name2 > name3`.

### Name format pattern
`/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/` — must start with a lowercase letter, segments separated by dots, each segment `[a-z][a-z0-9_]*`. Violations throw `\InvalidArgumentException`.

### Dangling anchor check
Decorators on a `ResolvedPlace` are names of other placements that wrap it. Any decorator name not found in the collected `seenNames` map is a `DanglingAnchorException`.
