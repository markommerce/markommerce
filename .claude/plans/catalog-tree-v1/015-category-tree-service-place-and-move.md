# Task 015: `CategoryTreeService` — node placement and move (with cycle detection)

**Status**: completed
**Depends on**: 014, 008, 003
**Retry count**: 0

## Description
Extend `CategoryTreeService` with node placement and move operations. Inject `CategoryTreeNodeRepositoryInterface` and `CategoryRepositoryInterface` (to validate referenced categories). Move detects cycles by walking the proposed parent chain.

## Context
- Modify existing file: `packages/catalog/src/Services/CategoryTreeService.php`
- Add constructor dependencies:
  - `CategoryTreeNodeRepositoryInterface $categoryTreeNodeRepository`
  - `CategoryRepositoryInterface $categoryRepository` (to validate category existence on `placeCategory`)
- Add a class constant for the default position gap: `private const int POSITION_GAP = 10;` (shared with `reorderSiblings` in task 016).
- New methods:
  - `placeCategory(int $treeId, int $categoryId, ?int $parentNodeId = null, ?int $position = null): CategoryTreeNode`
    - Verifies tree exists (throws `CategoryTreeNotFoundException`)
    - Verifies category exists (throws `CategoryNotFoundException`)
    - If `$parentNodeId` is non-null: verifies the parent node exists (throws `CategoryTreeNodeNotFoundException`) AND belongs to the same tree (throws `NodeNotInTreeException::forNodeAndTree`)
    - If `$position` is null, auto-assigns `max(siblings.position) + POSITION_GAP`, or 0 when no siblings exist
    - Allows the same category to be placed multiple times (multi-placement)
  - `moveNode(int $nodeId, ?int $newParentNodeId, int $position): void`
    - Verifies node exists (throws `CategoryTreeNodeNotFoundException`)
    - If `$newParentNodeId === $nodeId`: throws `CircularNodeReferenceException::forNodeAndParent($nodeId, $newParentNodeId)`
    - If `$newParentNodeId` is non-null: verifies parent exists (throws `CategoryTreeNodeNotFoundException`), belongs to the same tree as the moving node (throws `NodeNotInTreeException::forNodeAndTree`), and walking up from `$newParentNodeId` does NOT pass through `$nodeId` (throws `CircularNodeReferenceException`)
    - If `$newParentNodeId` is null: skip cycle detection (always safe — moves to root of the node's tree)
    - Updates `parent_node_id` and `position`
- Cycle detection algorithm: starting from `$newParentNodeId`, walk via `parent_node_id` until reaching null root; if `$nodeId` is encountered en route, throw `CircularNodeReferenceException::forNodeAndParent($nodeId, $newParentNodeId)`.
- Cache invalidation: both `placeCategory` and `moveNode` MUST unset the affected tree's entry in the materialised-tree cache before returning. `placeCategory` receives `$treeId` directly. `moveNode` must load the moving node first (`->find($nodeId)`) to read its `treeId` for invalidation.
- Test file: `packages/catalog/tests/Unit/Services/CategoryTreeServicePlaceAndMoveTest.php`
- A shared helper (e.g. `tests/Support/makeCategoryTreeService.php`) should construct the service with all four fakes; reuse from task 014.

## Requirements (Test Descriptions)
- [x] `placeCategory creates a root node when parent is null`
- [x] `placeCategory creates a child node under the given parent`
- [x] `placeCategory allows the same category to be placed twice in the same tree (multi-placement)`
- [x] `placeCategory assigns the next available position (max + POSITION_GAP) when position is null`
- [x] `placeCategory respects an explicitly provided position value`
- [x] `placeCategory throws CategoryTreeNotFoundException when tree id is unknown`
- [x] `placeCategory throws CategoryNotFoundException when category id is unknown`
- [x] `placeCategory throws CategoryTreeNodeNotFoundException when parentNodeId is provided but the parent node does not exist`
- [x] `placeCategory throws NodeNotInTreeException when parent node belongs to a different tree`
- [x] `moveNode updates the parent and position of a node`
- [x] `moveNode promotes a node to root when new parent is null`
- [x] `moveNode throws CategoryTreeNodeNotFoundException when node id is unknown`
- [x] `moveNode throws CategoryTreeNodeNotFoundException when newParentNodeId is non-null but the parent node does not exist`
- [x] `moveNode throws NodeNotInTreeException when the new parent belongs to a different tree`
- [x] `moveNode throws CircularNodeReferenceException when newParentNodeId equals nodeId (self-parent)`
- [x] `moveNode throws CircularNodeReferenceException when the move would create a cycle (parent under direct child)`
- [x] `moveNode throws CircularNodeReferenceException for a deeper cycle (grandparent under grandchild)`
- [x] `all previously added tests in CategoryTreeServiceTreeCrudTest and CategoryTreeServiceMarketResolutionTest continue to pass with the expanded constructor`

## Acceptance Criteria
- Service methods added without breaking previously added tests
- Cycle detection verified at depths 0 (self), 1 (direct child), and ≥2 (deeper)
- All `@throws` PHPDoc tags present
- PHPStan level 8 clean

## Implementation Notes
- Auto-position algorithm: call `$this->categoryTreeNodeRepository->findChildren($parentNodeId, $treeId)`, take the maximum `position`, add `POSITION_GAP`. If the list is empty, start at 0.
- The `POSITION_GAP` constant is shared with `reorderSiblings` in task 016 — define it once in this task.
