# Task 011: `CategoryTreeNodeRepository` concrete implementation (Postgres)

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Implement the concrete `CategoryTreeNodeRepository` covering all interface methods, ordered children queries, and multi-placement lookups. Integration-tested against real Postgres.

## Context
- Target file: `packages/catalog/src/Repositories/CategoryTreeNodeRepository.php`
- Extends `Marko\Database\Repository\Repository` with `ENTITY_CLASS = CategoryTreeNode::class`
- `findChildren` MUST sort by `position ASC` — explicit ORDER BY required
- `findByCategoryAcrossTrees` is used by the category-deletion guard; must return all placements regardless of tree
- Integration test: `packages/catalog/tests/Feature/Repositories/CategoryTreeNodeRepositoryIntegrationTest.php`
- Use the Postgres test infrastructure pattern from `config-pgsql`

## Requirements (Test Descriptions)
- [x] `it persists a node and reads it back by id`
- [x] `it finds all nodes belonging to a tree`
- [x] `it finds direct children of a parent node sorted by position`
- [x] `it finds root nodes of a tree (parent_node_id is null) sorted by position`
- [x] `it finds all placements of a category within a tree (multi-placement)`
- [x] `it finds all placements of a category across every tree`
- [x] `it deletes a node`

## Acceptance Criteria
- Concrete class with ENTITY_CLASS const
- Integration test passes against real Postgres
- Position ordering verified in both ordered-query tests
- PHPStan level 8 clean

## Implementation Notes
- `CategoryTreeNodeRepository` extends `Repository<CategoryTreeNode>` and implements `CategoryTreeNodeRepositoryInterface`
- `findByTree`, `findByCategoryInTree`, and `findByCategoryAcrossTrees` delegate to `findBy()` (inherited from `Repository`)
- `findChildren` uses raw SQL with explicit `ORDER BY position ASC` to guarantee ordering; null `parentNodeId` produces `IS NULL` predicate
- `findRoots` is a one-liner delegating to `findChildren(null, $treeId)`
- Integration test creates two trees and three categories in setup, then cleans up in teardown using DELETE ordering that respects FK constraints (nodes → categories → trees)
- PHPStan required `/** @var list<CategoryTreeNode> */` cast plus `array_values()` wrapping on `findChildren` return to satisfy the `list<>` type contract
