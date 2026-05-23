# Task 005: Source Vocabulary

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create the closed set of typed `Source` value objects and the `Source` static factory. A Source declares where a placement prop's value comes from. Each Source is a pure value object carrying enough metadata for the compiler to type-check it and for a runtime resolver (task 012) to produce the value.

## Context
- The factory `Source` exposes static constructors; each returns a distinct value object:
  - `Source::route(string $name, string $as = 'string')` → `RouteSource` — `as` is one of `int|string|bool`.
  - `Source::query(string $name, mixed $default = null, string $as = 'string')` → `QuerySource`.
  - `Source::context(string $token, ?string $path = null)` → `ContextSource` — `token` is a marker `class-string`; `path` is an optional dot-path into the context value.
  - `Source::iterated(string $token, ?string $path = null)` → `IteratedSource` — `token` is an iteration-token `class-string`.
  - `Source::parentData(string $key, string $as = 'string')` → `ParentDataSource` — reads a key from the immediate parent placement's `data()` DTO.
  - `Source::service(string $class)` → `ServiceSource` — a DI `class-string`.
- Literal scalars/arrays passed directly as a prop value are NOT Sources — the compiler treats any non-`Source` prop value as a literal. No `LiteralSource` class needed; document this in Implementation Notes.
- All Source objects implement a common `Source` marker interface (separate from the factory if the factory is a class with static methods — resolve the naming: e.g. interface `SourceInterface`, factory `Source`). Decide and document.
- The `as` cast keyword set is closed: `int`, `string`, `bool`. Reject anything else at construction with a clear exception.
- Source objects are `readonly class`, live in `src/Source/`.
- Patterns to follow: `code-standards.md` typed-constants rule (the `as` allowlist should be typed constants).

## Requirements (Test Descriptions)
- [ ] `it builds a route source with a name and cast type`
- [ ] `it builds a query source with a name, default and cast type`
- [ ] `it builds a context source with a token class and optional dot path`
- [ ] `it builds an iterated source with an iteration token and optional dot path`
- [ ] `it builds a parent-data source with a key and cast type`
- [ ] `it builds a service source with a class name`
- [ ] `it rejects an unknown cast keyword on a route source`
- [ ] `it rejects an unknown cast keyword on a query source`
- [ ] `it marks every source object with the common source interface`
- [ ] `it treats a non-source prop value as a literal`

## Acceptance Criteria
- All requirements have passing tests
- The cast keyword allowlist is a typed constant
- All Source objects are `readonly class` implementing the common marker interface
- Literal-vs-Source distinction documented in Implementation Notes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
