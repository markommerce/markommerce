# Task 021: End-to-end feature test

**Status**: completed
**Depends on**: 020, 017, 019
**Retry count**: 0

## Description
Write a feature test that exercises the full lifecycle against the real Postgres driver: create a tree, place categories in it, assign it to a market, resolve the tree for that market, materialize it, and confirm the structure matches expectations.

## Context
- Target file: `packages/catalog/tests/Feature/CategoryTreeIntegrationTest.php`
- Uses the Postgres test infrastructure pattern from `config-pgsql`
- Resolves the `CategoryTreeService` through the Marko container (asserts module bindings work)

## Requirements (Test Descriptions)
- [x] `it creates a non-default tree, places categories, assigns it to a market, and resolves the tree for that market`
- [x] `it resolves the default tree for a market with no assignment`
- [x] `it materializes the tree with correct nesting and position order against the real database`
- [x] `it permits the same category to be placed twice within one tree (multi-placement)`
- [x] `it prevents deleting a category that has placements (CategoryHasPlacementsException)`
- [x] `it detects a cycle when attempting to move a node under its own descendant`

## Acceptance Criteria
- Feature test passes against real Postgres
- Test cleans up after itself (uses transactions or explicit teardown)
- PHPStan level 8 clean

## Implementation Notes
- `CategoryTreeService` is a concrete class with four repository-interface dependencies. The test resolves it via the Marko container; if the container does not auto-resolve concrete classes, add the concrete-class binding under task 020 (`CategoryTreeService::class => CategoryTreeService::class`). Existing services (`CategoryAssignmentService`, `ProductService`) are not in `module.php`, suggesting auto-resolution works — verify in this task.
- The cycle-detection test should construct a 3-level tree (root → child → grandchild) and attempt `moveNode(rootId, newParentNodeId: grandchildId, position: 0)`. This must throw `CircularNodeReferenceException`.
- The multi-placement test should call `placeCategory` twice for the same `(categoryId, treeId)` pair and assert the two returned nodes have distinct `id`s but the same `categoryId`.
