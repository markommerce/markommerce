# Task 011: Compiled Artifact — PreparedTree & Reader/Writer

**Status**: completed
**Depends on**: 009
**Retry count**: 0

## Description
Create the `PreparedTree` value object (the flat, fully-resolved representation of a layout that the runtime renders) and the artifact writer/reader pair. The writer serializes `array<handleKey, PreparedTree>` to `var/cache/markommerce/layouts.php`; the reader loads it back on a request with a single `require`.

## Context
- `PreparedTree` is the runtime-facing form: a tree of prepared placement nodes, each carrying its component class, resolved prop sources, decorator chain, and sub-slots (keyed + repeat). It must be fully serializable to a PHP literal.
- The writer emits a PHP file that `return`s an `array<handleKey, PreparedTree>` literal. The `Source` objects, `Place`, `Slot`, `Provide`, decorator markers, and `PreparedTree` nodes in the artifact must all round-trip.
- IMPORTANT — serialization strategy for `readonly` value objects: `var_export()` emits `\ClassName::__set_state([...])` for objects, and `__set_state()` is invoked on an *already-instantiated* object via property assignment — which **fails for `readonly` properties** outside the constructor. So a plain `__set_state` on these readonly value objects will throw at artifact-load time. Two viable approaches; pick ONE and apply it consistently:
  1. **Custom code emitter** (recommended): a serializer that walks the tree and emits literal `new \Fully\Qualified\ClassName(arg, arg, ...)` constructor-call source for every value object, recursing into nested objects/arrays. This sidesteps `__set_state` entirely and works with readonly + constructor-promoted properties.
  2. **Static `fromState()` factory** on each value object plus an emitter that calls it — equivalent, more boilerplate.
  Do NOT rely on bare `var_export()` + `__set_state` for the value objects. Document the chosen approach and verify the round-trip in a test that actually `require`s the written file.
- Every value object that can appear in the artifact must be enumerable by the emitter — keep the set closed (the `Source` vocabulary, `Place`, `Slot`, `Provide`, decorator marker, `PreparedTree`). If a future object type is added it must be registered with the emitter; note this coupling.
- The artifact is plain PHP returning an array — loading it is one `require`. No `unserialize()`, no JSON.
- The reader: given `var/cache/markommerce/layouts.php`, return the `array<handleKey, PreparedTree>`; throw a clear error if the file is missing (suggest running `layout:compile`).
- Artifact path is `var/cache/markommerce/layouts.php` relative to the project root. The writer must `mkdir -p` the directory.
- This task does NOT run the compiler — it takes an already-resolved tree (task 009 output) and is the serialization boundary. The full compiler wiring (discovery → resolution → validation → write) is assembled in task 014.
- Patterns to follow: how Marko caches compiled artifacts (check `marko/core` or `marko/routing` for a route-cache precedent); `code-standards.md`.

## Requirements (Test Descriptions)
- [ ] `it builds a PreparedTree from a resolved layout tree`
- [ ] `it preserves repeat slots in a PreparedTree`
- [ ] `it preserves decorator chains in a PreparedTree`
- [ ] `it writes an artifact file that returns an array of PreparedTrees`
- [ ] `it round-trips Source objects through the artifact`
- [ ] `it creates the cache directory when writing if it does not exist`
- [ ] `it reads a written artifact back into PreparedTrees`
- [ ] `it throws a clear error when reading a missing artifact`

## Acceptance Criteria
- All requirements have passing tests
- The artifact is a plain PHP file loaded with one `require`
- `Source` and all value objects round-trip exactly
- Serialization strategy (`__set_state` vs custom emitter) documented in Implementation Notes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
