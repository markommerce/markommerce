# Task 030: Cross-handle conflict validation

**Status**: completed
**Depends on**: 029
**Retry count**: 0

## Description
Extend the compile-time `ValidationPhase` so it catches conflicts that can arise once trees are mergeable: duplicate placement names across a base handle and any handle declared by its `HandleProvider`s, and duplicate context-bag tokens across the same set. Catch these errors at compile time wherever statically possible; defer only to runtime when the dynamic-handle target genuinely cannot be known at compile time.

## Context
- **Static-known providers**: a provider class that exposes a `#[ProvidesHandles('handle.a', 'handle.b')]` PHP attribute (defined in this task at `packages/layout/src/Attributes/ProvidesHandles.php`) declares its full return set at compile time. ValidationPhase reflects on the provider class via `ReflectionClass::getAttributes(ProvidesHandles::class)` and validates the pairwise conflict at compile time.
- **Opaque providers** (no attribute): the provider can return any handle string. ValidationPhase cannot statically detect collisions; instead, it records the base tree's set of placement names into the artifact (a new `placementNames: list<string>` field on `PreparedTree`, or a sidecar map in the emitted artifact), so the runtime `TreeMerger` can fail loudly on collision with `DynamicHandleConflictException` (from task 023).
- **Chained handle providers**: validate that no statically-known dynamic handle's tree itself declares `handleProviders`. For opaque providers, this check is impossible at compile time; record the "no chained providers" rule in the artifact so the runtime merger throws `ChainedHandleProviderException` (from task 023) if it encounters one during merge.
- **Dynamic-handle context-token conflicts**: extend the validation to catch cases where the base tree's `context` and a statically-known dynamic handle's `context` share a token. Throw `DuplicateContextTokenException` (from task 023). For opaque providers, defer to a runtime check in `TreeMerger`.
- This task does NOT cover validating the `default`-handle merge — that's already in task 027.
- This task DOES touch `PreparedTree` to add the `placementNames` field (or equivalent), and `PhpCodeEmitter` to serialize it. `TreeMerger` (task 029) reads it.
- Ordering note: 029 builds `TreeMerger` without runtime collision detection; 030 enhances `TreeMerger` to consult the recorded `placementNames` set and throw on collision. This task is therefore correctly sequenced after 029.

## Requirements (Test Descriptions)
- [x] `it defines a #[ProvidesHandles(...)] PHP attribute that providers can use to declare their static return set`
- [x] `it throws DynamicHandleConflictException at compile time when a statically-known dynamic handle declares a placement that conflicts with the base handle`
- [x] `it throws DynamicHandleConflictException at runtime when an opaque provider returns a handle whose tree conflicts with the base`
- [x] `it throws DuplicateContextTokenException at compile time when a statically-known dynamic handle and the base handle share a context token`
- [x] `it records the base tree's placement-name set in the artifact for runtime conflict detection`
- [x] `it passes validation when dynamic and base trees declare disjoint placement names`
- [x] `it throws ChainedHandleProviderException at compile time when a statically-known dynamic handle's tree declares its own handleProviders`
- [x] `it throws ChainedHandleProviderException at runtime when an opaque provider returns a handle whose tree declares handleProviders`

## Acceptance Criteria
- All requirements have passing tests
- Compile errors include the file path and line of the offending declaration
- PHPStan level 8 clean

## Implementation Notes
- Created `packages/layout/src/Attributes/ProvidesHandles.php` — a PHP 8 `#[Attribute]` targeting classes, with a `list<string> $handles` constructor parameter.
- Added `placementNames: list<string>` field to `PreparedTree` (default `[]`), emitted by `PhpCodeEmitter`, populated by `PreparedTreeBuilder::collectPlacementNames()`.
- Extended `ValidationPhase::validate()` with `validateCrossHandleConflicts()` which iterates layouts with `handleProviders`, reflects on provider classes for `#[ProvidesHandles]`, and performs pairwise compile-time checks: placement conflicts (`DynamicHandleConflictException`), context-token conflicts (`DuplicateContextTokenException`), and chained providers (`ChainedHandleProviderException`). Opaque providers (no attribute) are skipped at compile time.
- Extended `TreeMerger::merge()` with runtime checks: any addition with non-empty `handleProviders` throws `ChainedHandleProviderException`; any addition whose named placements collide with `base.placementNames` throws `DynamicHandleConflictException`.
