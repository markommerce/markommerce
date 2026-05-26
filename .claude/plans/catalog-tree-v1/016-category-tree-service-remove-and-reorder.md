# Task 016: `CategoryTreeService` — node removal and sibling reorder

**Status**: completed
**Depends on**: 015, 001
**Retry count**: 0

## Description
Extend `CategoryTreeService` with explicit node removal (caller chooses `NodeRemovalStrategy::CASCADE` or `NodeRemovalStrategy::PROMOTE_CHILDREN`) and sibling reordering. Both mutators invalidate the in-request materialised-tree cache for the affected tree.

## Context
- Modify existing file: `packages/catalog/src/Services/CategoryTreeService.php`
- New methods:
  - `removeNode(int $nodeId, NodeRemovalStrategy $strategy): void`
    - Loads the node first (`find`); throws `CategoryTreeNodeNotFoundException::forId` if absent. The node's `treeId` is captured for cache invalidation after the removal completes.
    - `CASCADE`: recursively delete the node and all descendants (post-order traversal — delete leaves before parents to honour FK).
    - `PROMOTE_CHILDREN`: re-parent direct children to the removed node's parent, preserving their relative position order, then delete the node. If the removed node is a root (`parent_node_id IS NULL`), promoted children become new roots.
  - `reorderSiblings(?int $parentNodeId, int $treeId, array $orderedNodeIds): void`
    - For every node id in `$orderedNodeIds`:
      - Load the node (throws `CategoryTreeNodeNotFoundException::forId` if any id is unknown)
      - Verify `node->treeId === $treeId` (throws `NodeNotInTreeException::forNodeAndTree`)
      - Verify `node->parentNodeId === $parentNodeId` (throws `NodeNotInTreeException::forParentMismatch` — new factory added in task 003)
    - Update `position` on each listed sibling according to its order in the array: 0, `POSITION_GAP`, `2 * POSITION_GAP`, …
  - Both methods invalidate `materializedTreeCache[$treeId]` (or the discovered tree id, in the case of `removeNode`) before returning.
- Test file: `packages/catalog/tests/Unit/Services/CategoryTreeServiceRemoveAndReorderTest.php`

## Requirements (Test Descriptions)
- [x] `removeNode with CASCADE deletes the node`
- [x] `removeNode with CASCADE deletes all descendants recursively`
- [x] `removeNode with PROMOTE_CHILDREN deletes the node and reparents direct children to its parent`
- [x] `removeNode with PROMOTE_CHILDREN preserves the order of promoted children`
- [x] `removeNode with PROMOTE_CHILDREN against a root node makes its children new roots`
- [x] `removeNode throws CategoryTreeNodeNotFoundException when node id is unknown`
- [x] `reorderSiblings updates positions to match the provided order using POSITION_GAP spacing`
- [x] `reorderSiblings throws CategoryTreeNodeNotFoundException when any listed id is unknown`
- [x] `reorderSiblings throws NodeNotInTreeException when a listed node belongs to a different tree`
- [x] `reorderSiblings throws NodeNotInTreeException (forParentMismatch) when a listed node has a different parent than expected`
- [x] `all previously added service tests continue to pass`

## Acceptance Criteria
- Strategy enum import from task 001
- New `NodeNotInTreeException::forParentMismatch` factory (added under task 003) used for parent-uniformity validation
- All `@throws` PHPDoc tags present
- PHPStan level 8 clean

## Implementation Notes
- Position spacing constant `POSITION_GAP` is declared in task 015; reuse here.
- Post-order CASCADE traversal: depth-first walk to the leaves, delete on the way back up. Use `findChildren($currentId, $treeId)` recursively.
- PROMOTE_CHILDREN ordering: load the direct children sorted by position (the repo's `findChildren` already sorts). Their relative order is preserved by reusing their existing positions; the caller can call `reorderSiblings` afterward if monotonic spacing is desired.
