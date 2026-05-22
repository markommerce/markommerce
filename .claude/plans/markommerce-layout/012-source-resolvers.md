# Task 012: Source Runtime Resolvers

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Create the runtime resolvers that turn each `Source` value object into an actual value at render time. One resolver strategy per Source kind, dispatched by Source type. This is the data-flow engine of the renderer.

## Context
- A `SourceResolver` takes a `Source` plus a `ResolutionContext` and returns the resolved value. `ResolutionContext` carries everything a resolver might need: the `Request`, route parameters, the page context map (`array<class-string, object>` keyed by token), the current iteration item (for `iterated` sources), the parent placement's `data()` DTO (for `parentData` sources), the DI container (`Marko\Core\Container\ContainerInterface` — for `service` sources), and the placement chain string (for loud-error `context`).
- One resolver per kind:
  - `RouteSource` → route parameter, cast to `as` type. If the param is absent or not castable → `InvalidSourceTypeException`.
  - `QuerySource` → request query value or `default`, cast to `as`.
  - `ContextSource` → look up `token` in the context map; if `path` set, walk the dot-path into the value. Missing token at runtime → loud error (should have been caught at compile, but defend).
  - `IteratedSource` → the current iteration item; if `path` set, walk into it.
  - `ParentDataSource` → read `key` from the parent `data()` DTO (a public property), cast to `as`.
  - `ServiceSource` → resolve the class from the DI container.
- Literal prop values (non-Source) pass through unchanged — the dispatcher returns them as-is.
- The dispatcher maps Source type → resolver. Keep it a closed `match` on concrete Source classes (the vocabulary is closed).
- Dot-path walking (`ContextSource`/`IteratedSource` `path`) walks public properties (and array keys) — implement a small helper; missing segment → loud error naming the path and the available keys.
- `InvalidSourceTypeException` (task 002) is the runtime cast-failure error; it must carry the placement chain — accept the chain via `ResolutionContext`.
- Patterns to follow: `marko/layout/src/ComponentDataResolver.php` (the thing being replaced — note its `castToType` helper), `code-standards.md`.

## Requirements (Test Descriptions)
- [x] `it resolves a route source casting the value to its target type`
- [x] `it resolves a query source falling back to the default when absent`
- [x] `it resolves a context source by token`
- [x] `it resolves a context source walking a dot path into the value`
- [x] `it resolves an iterated source to the current iteration item`
- [x] `it resolves a parent-data source by reading a key from the parent data DTO`
- [x] `it resolves a service source from the container`
- [x] `it passes a literal prop value through unchanged`
- [x] `it throws InvalidSourceTypeException when a route value cannot be cast`
- [x] `it throws a loud error when a dot path segment does not exist`

## Acceptance Criteria
- All requirements have passing tests
- The dispatcher is a closed `match` over Source classes
- Runtime errors carry the placement chain
- Code follows code standards

## Implementation Notes
- `ResolutionContext` is a `readonly class` at `packages/layout/src/Runtime/ResolutionContext.php`. Holds `Request`, `routeParams`, `contextMap`, `iterationItem`, `parentData`, nullable `ContainerInterface`, and `placementChain`.
- `SourceResolver` at `packages/layout/src/Runtime/SourceResolver.php` dispatches via a closed `match(true)` over concrete Source classes, with a literal pass-through for non-`SourceInterface` values.
- Dot-path walking in `walkPath()` handles both arrays (by key) and objects (by public property), throwing `RuntimeException` with available keys on missing segments.
- `castToType()` uses `is_numeric()` to guard int casts, throwing `InvalidSourceTypeException` on failure.
- `resolveService()` guards against a null container with a loud `RuntimeException`.
- All methods have multiline signatures (PHPCS Slevomat rule) and pass PHPStan level 8.
