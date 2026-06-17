# Task 003: `IndexerInterface` + `AbstractIndexer` + scope-pass helper (core)

**Status**: done
**Depends on**: 001, 002
**Retry count**: 0

## Description
In `markommerce/indexer`, define the indexer contract and a reusable base that owns the chunked
rebuild/reindex lifecycle and the per-signature scope-pass mechanics (scope-context save/restore),
delegating row PRODUCTION to subclasses. This is the shared core both the price and attribute
indexers consume.

## Context
- Pattern: `packages/catalog-price-index/src/PriceIndexer.php` (chunked `rebuildAll`, per-scope
  passes, scope-context save/restore lines ~29–47, 70–121). STUDY it — extract the reusable shape.
- Place in `packages/indexer/src/`.
- `IndexerInterface`: `reindex(array $ids): int`, `reindexOne(int $id): int`, `rebuildAll(int $chunkSize = 500): int`.
- `AbstractIndexer implements IndexerInterface`: owns
  - `rebuildAll`: enumerate all entity ids (abstract `allIds(): iterable<int>`), chunk, call `indexChunk`.
  - `reindex(ids)`: chunk the given ids, call `indexChunk`.
  - returns the count of index rows written.
  - delegates to abstract `indexChunk(array $ids): int` (subclass produces + persists rows for the chunk).
- **Scope-pass helper** (separate injectable, e.g. `ScopePassRunner`): signature
  `each(list<ScopeSignature> $signatures, callable $fn): void`. Behavior:
  - SAVE the full ambient state up-front: `$saved = $scopeContext->state()` (the
    `array<string,string>` axis→path map — NOT just one axis; the attribute indexer is multi-axis,
    so the single-axis `get('market')` save/restore of the price indexer is INSUFFICIENT here).
  - Base pass first: `$scopeContext->clearAll()` then invoke `$fn(null)` (the base/global pass is
    signalled by passing `null`, NOT a `ScopeSignature` — `ScopeSignature` cannot represent the empty
    signature, its constructor throws on `[]`). The subclass maps `null` → the `''` signature column.
  - Then for each `ScopeSignature`: `$scopeContext->clearAll()`, apply each axis via
    `$scopeContext->in($axis, $signature->get($axis))` for `$signature->axes()`, invoke
    `$fn($signature)`.
  - In a `finally`, RESTORE: `$scopeContext->clearAll()` then re-apply every `$saved` axis via
    `in()`. This guarantees a reindex never corrupts a concurrent caller's ambient scope, including
    on exception. (Inject `ScopeContext`.)
- The callback receives `?ScopeSignature` — `null` for the base/global pass, a concrete signature
  otherwise. Subclasses decide how to use it (e.g. map `null` → `''`, else `$sig->toString()`).
- Do NOT impose a row shape or accumulation model — `indexChunk` returns/persists whatever rows the
  subclass builds.

## Requirements (Test Descriptions)
- [x] `it chunks all ids and indexes each chunk during rebuildAll`
- [x] `it indexes only the given ids during reindex`
- [x] `it returns the total count of indexed rows`
- [x] `it runs the callback once with null for the base pass plus once per signature`
- [x] `it clears all axes before the base pass and sets every signature axis before each scoped pass`
- [x] `it restores the full multi-axis ambient scope context after the passes complete`
- [x] `it restores the ambient scope context even when a pass throws`

## Acceptance Criteria
- `AbstractIndexer` owns chunking + lifecycle; subclasses implement `allIds`/`indexChunk` only.
- The scope-pass helper save/restores `ScopeContext` (including on exception).

## Implementation Notes
- `AbstractIndexer` placed in `packages/indexer/src/AbstractIndexer.php`. Implements `IndexerInterface`.
  `rebuildAll` collects `allIds()` iterable into an array then chunks with `array_chunk`.
  `reindex` chunks the given ids array. `reindexOne` delegates to `reindex([id])`.
- `ScopePassRunner` placed in `packages/indexer/src/ScopePassRunner.php`. Injects `ScopeContext`.
  `each()` saves full `state()` snapshot, runs base pass (clearAll + fn(null)), then one scoped pass
  per signature (clearAll + set each axis + fn($sig)), restores in `finally` block.
- Both use hand-written fakes for `ScopeRegistryInterface` in tests (no Docker DB needed).
