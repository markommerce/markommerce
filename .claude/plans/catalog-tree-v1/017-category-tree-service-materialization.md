# Task 017: `CategoryTreeService` — materialized tree with in-request memoisation

**Status**: completed
**Depends on**: 016
**Retry count**: 0

## Description
Add `getMaterializedTree(int $treeId): array` to `CategoryTreeService`. Single query fetches all nodes for the tree; PHP assembles a nested structure sorted by position. Result is memoised per `tree_id` in a private array on the service instance (in-request cache). Cache invalidates on any structural change.

## Context
- Modify existing file: `packages/catalog/src/Services/CategoryTreeService.php`
- Private property: `private array $materializedTreeCache = [];` keyed by `tree_id`
- Method signature: `getMaterializedTree(int $treeId): array`
  - Returns nested array of the form:
    ```
    [
      ['node' => CategoryTreeNode, 'category_id' => 7, 'children' => [
          ['node' => CategoryTreeNode, 'category_id' => 12, 'children' => []]
      ]],
      ...
    ]
    ```
  - Roots returned in position order; children at every level in position order
- Throws `CategoryTreeNotFoundException` if tree does not exist
- Cache invalidation contract (already wired in tasks 015 and 016, this task only verifies it):
  - `placeCategory(treeId, …)` — invalidate `materializedTreeCache[$treeId]` (treeId is an argument).
  - `moveNode(nodeId, …)` — first `find($nodeId)` to read its `treeId`, then invalidate `materializedTreeCache[$treeId]`.
  - `removeNode(nodeId, strategy)` — same as `moveNode`: load the node, capture `treeId`, then perform removal, then invalidate.
  - `reorderSiblings(parentNodeId, treeId, …)` — invalidate `materializedTreeCache[$treeId]`.
- Test file: `packages/catalog/tests/Unit/Services/CategoryTreeServiceMaterializationTest.php`

## Requirements (Test Descriptions)
- [ ] `getMaterializedTree returns roots in position order`
- [ ] `getMaterializedTree returns children at every depth in position order`
- [ ] `getMaterializedTree returns an empty array for a tree with no nodes`
- [ ] `getMaterializedTree throws CategoryTreeNotFoundException for an unknown tree id`
- [ ] `getMaterializedTree memoises within a request — repeated calls do not re-query the node repository`
- [ ] `placeCategory invalidates the cached materialization for the affected tree`
- [ ] `moveNode invalidates the cached materialization for the affected tree`
- [ ] `removeNode invalidates the cached materialization for the affected tree`
- [ ] `reorderSiblings invalidates the cached materialization for the affected tree`

## Acceptance Criteria
- Single-query node fetch (assert by inspecting the fake's call log if available; otherwise verify memoisation via call-count test)
- Cache cleanly invalidated on each mutator (the invalidation code is added in tasks 015 and 016; this task verifies the contract with focused tests)
- Structural shape of the returned array matches the spec
- All previously added service tests continue to pass
- PHPStan level 8 clean

## Implementation Notes
- To verify "single-query node fetch", extend `FakeCategoryTreeNodeRepository` with a public `array $callLog = []` and append to it inside `findByTree`. The test asserts that two consecutive `getMaterializedTree` calls increment the call log by exactly 1.
- The nested array shape is hard to type under PHPStan level 8 (`array<int, array{node: CategoryTreeNode, category_id: int, children: array<…>}>` requires recursion). Use a `@return array<int, array{node: CategoryTreeNode, category_id: int, children: array<int, mixed>}>` PHPDoc with a `@phpstan-ignore` if the recursion confuses the analyser; leave a note for a v2 conversion to a value object.
