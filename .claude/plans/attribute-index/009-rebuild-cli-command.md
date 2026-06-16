# Task 009: Register the attribute indexer in the `IndexerRegistry`

**Status**: done
**Depends on**: 004, 007
**Retry count**: 0

## Description
Make the attribute index rebuildable through the unified `index:rebuild` command by registering the
`AttributeIndexer` under the name `attribute` in the core `IndexerRegistry`. No per-index command
class this phase — the shared `index:rebuild [name?]` command (task 004) is the entry point.

## Context
- There is NO `RebuildAttributeIndexCommand` — Phase 4 uses the unified core command. This task wires
  the registration (in the `catalog-attribute-index` module boot) and proves the command path.
- In `packages/catalog-attribute-index/module.php` boot, resolve the `IndexerRegistry` (task 004) and
  call `register('attribute', $attributeIndexer)`. (The price index registers `price` in task 005.)
- Verify the unified command resolves and rebuilds the attribute index: `index:rebuild attribute`
  rebuilds only the attribute index; `index:rebuild` (no name) includes it among all indexes.
- Name choice: `attribute` (short, matches `price`). Document it.

## Requirements (Test Descriptions)
- [x] `it registers the AttributeIndexer under the name attribute in the indexer registry after boot`
- [x] `it rebuilds the attribute index via the unified index:rebuild attribute command`
- [x] `it includes the attribute index when index:rebuild runs with no name`

## Acceptance Criteria
- `AttributeIndexer` is registered as `attribute`; the unified command rebuilds it by name and as part of "all".
- No separate attribute command class is introduced.

## Implementation Notes
- Added a `boot` function to `packages/catalog-attribute-index/module.php` that receives `IndexerRegistry`
  and `AttributeIndexer` via DI and calls `$indexerRegistry->register('attribute', $attributeIndexer)`.
- Test file: `packages/catalog-attribute-index/tests/Unit/ModuleBootRegistrationTest.php`
- The test container pre-binds a stub `AttributeIndexer` (anonymous class extending it with no-arg constructor)
  so the boot can be called without wiring all real infrastructure dependencies.
- All three requirements pass; no separate command class introduced.
