# Task 005: `CategoryTreeNode` entity

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create the `CategoryTreeNode` entity representing a placement of a category at a position within a tree. The `parent_node_id` references *another node* (not a category) to permit multi-placement of the same category within the same tree.

## Context
- Target file: `packages/catalog/src/Entity/CategoryTreeNode.php`
- Namespace: `Markommerce\Catalog\Entity`
- Pattern to mirror: existing `ProductCategoryAssignment` entity (FK references, `#[Index]`)
- Schema (via attributes):
  - `id` — primary key, auto-increment
  - `tree_id` — `#[Column(name: 'tree_id', references: 'catalog_category_trees', onDelete: 'CASCADE')]`
  - `category_id` — `#[Column(name: 'category_id', references: 'catalog_categories', onDelete: 'RESTRICT')]` (RESTRICT preserves the "category cannot be deleted if placed" invariant at the DB layer too)
  - `parent_node_id` — self-FK, nullable: `#[Column(name: 'parent_node_id', references: 'catalog_category_tree_nodes', onDelete: 'CASCADE', nullable: true)] public ?int $parentNodeId = null;`
  - `position` — `#[Column] public int $position = 0;`
- Table: `#[Table('catalog_category_tree_nodes')]`
- Indices:
  - `#[Index(name: 'uniq_node_position', columns: ['tree_id', 'parent_node_id', 'position'], unique: true)]`
  - `#[Index(name: 'idx_node_tree_category', columns: ['tree_id', 'category_id'])]`
  - `#[Index(name: 'idx_node_parent_position', columns: ['parent_node_id', 'position'])]`

## Requirements (Test Descriptions)
- [ ] `it can be instantiated with default values`
- [ ] `it defaults parentNodeId to null marking a root placement`
- [ ] `it defaults position to zero`
- [ ] `it exposes id, treeId, categoryId, parentNodeId, and position public properties`
- [ ] `it is mapped to the catalog_category_tree_nodes table`

## Acceptance Criteria
- Entity file in correct location and namespace
- Test file in `packages/catalog/tests/Unit/Entity/CategoryTreeNodeTest.php`
- Unique-position index declared
- Two non-unique indices declared (category lookup, children lookup)
- PHPStan level 8 clean

## Implementation Notes
- On PostgreSQL, NULL values in a unique index are treated as distinct (multiple rows with `parent_node_id=NULL` are allowed by the unique index `(tree_id, parent_node_id, position)` regardless of `position` collisions). The service layer is responsible for assigning unique `(tree_id, null, position)` tuples for root nodes via the auto-position logic in `placeCategory` (task 015).
