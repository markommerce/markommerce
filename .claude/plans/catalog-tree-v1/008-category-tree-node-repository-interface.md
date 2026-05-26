# Task 008: `CategoryTreeNodeRepositoryInterface` + fake implementation

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Define the contract for tree-node persistence and provide an in-memory `FakeCategoryTreeNodeRepository`. The interface MUST extend `Marko\Database\Repository\RepositoryInterface<CategoryTreeNode>` (mirroring existing `CategoryRepositoryInterface`). Only the *custom* methods are declared on the interface; base methods are inherited.

## Context
- Interface file: `packages/catalog/src/Contracts/CategoryTreeNodeRepositoryInterface.php`
- Fake file: `packages/catalog/tests/Support/FakeCategoryTreeNodeRepository.php`
- Pattern to mirror: existing `ProductCategoryAssignmentRepositoryInterface` (extends `RepositoryInterface<…>` and only adds custom methods)
- Custom methods to declare (the only methods to add):
  - `findByTree(int $treeId): list<CategoryTreeNode>` — all nodes for a tree
  - `findChildren(?int $parentNodeId, int $treeId): list<CategoryTreeNode>` — direct children sorted by position; `null` parent = roots
  - `findRoots(int $treeId): list<CategoryTreeNode>` — convenience shortcut for `findChildren(null, $treeId)`
  - `findByCategoryInTree(int $categoryId, int $treeId): list<CategoryTreeNode>` — multi-placement lookup
  - `findByCategoryAcrossTrees(int $categoryId): list<CategoryTreeNode>` — used by category deletion guard
- DO NOT redeclare `find`, `findAll`, `findBy`, `findOneBy`, `existsBy`, `save`, `delete`, `findOrFail`, `insertBatch` — they come from the base.
- Fake must implement the **full** base `RepositoryInterface<CategoryTreeNode>` contract (mirror `FakeProductCategoryAssignmentRepository`) and then add the custom methods.
- Sorted-by-position semantics MUST be honoured by fake (in-memory sort on read for `findChildren` and `findRoots`)

## Requirements (Test Descriptions)
- [x] `interface extends Marko\Database\Repository\RepositoryInterface`
- [x] `interface declares the five custom methods (findByTree, findChildren, findRoots, findByCategoryInTree, findByCategoryAcrossTrees)`
- [x] `fake stores a node on save and returns it from find by id`
- [x] `fake assigns an id when saving a node with null id`
- [x] `fake findByTree returns all nodes belonging to the tree`
- [x] `fake findChildren returns direct children of the given parent in position order`
- [x] `fake findChildren with null parent returns root nodes`
- [x] `fake findRoots is equivalent to findChildren with null parent`
- [x] `fake findByCategoryInTree returns all placements of a category within a tree (multi-placement)`
- [x] `fake findByCategoryAcrossTrees returns all placements regardless of tree`
- [x] `fake removes a node on delete`

## Acceptance Criteria
- Interface and fake in correct locations
- Interface extends `RepositoryInterface<CategoryTreeNode>` via PHPDoc `@extends`
- Test file `packages/catalog/tests/Unit/Repositories/FakeCategoryTreeNodeRepositoryTest.php`
- Sorted-by-position behaviour verified in tests
- Fake implements full `RepositoryInterface` contract (signatures matching the base verbatim)
- PHPStan level 8 clean

## Implementation Notes
- `findByCategoryAcrossTrees` is intentionally row-returning rather than boolean because callers (the category deletion guard in task 018) need the count for diagnostics in `CategoryHasPlacementsException`. If profiling reveals hot-path concerns later, a dedicated `countByCategory(int $categoryId): int` method can be added in a future task.
