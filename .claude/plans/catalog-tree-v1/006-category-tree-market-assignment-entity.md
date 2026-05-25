# Task 006: `CategoryTreeMarketAssignment` entity

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create the `CategoryTreeMarketAssignment` entity binding a market identifier to a tree. The `market` column is the primary key — one tree per market.

## Context
- Target file: `packages/catalog/src/Entity/CategoryTreeMarketAssignment.php`
- Namespace: `Markommerce\Catalog\Entity`
- Pattern to mirror: existing entities, but the PK is a string column, not auto-increment integer
- Schema (via attributes):
  - `market` — `#[Column(primaryKey: true, length: 64)] public string $market = '';`
  - `tree_id` — `#[Column(name: 'tree_id', references: 'catalog_category_trees', onDelete: 'RESTRICT')]` (RESTRICT preserves the "tree cannot be deleted if it has market assignments" invariant at the DB layer)
- Table: `#[Table('catalog_category_tree_market_assignments')]`

## Requirements (Test Descriptions)
- [x] `it can be instantiated with default values`
- [x] `it exposes market and treeId public properties`
- [x] `it uses market as the primary key column`
- [x] `it is mapped to the catalog_category_tree_market_assignments table`

## Acceptance Criteria
- Entity file in correct location and namespace
- Test file in `packages/catalog/tests/Unit/Entity/CategoryTreeMarketAssignmentTest.php`
- The `market` column carries the `primaryKey: true` attribute argument
- PHPStan level 8 clean

## Implementation Notes
- Entity uses a string primary key (`market`, length 64) instead of the auto-increment integer pattern used by other entities
- `treeId` is nullable (`?int`) with `onDelete: 'RESTRICT'` to enforce the DB-level constraint that a tree with market assignments cannot be deleted
- All 4 tests pass; PHPStan level 8 clean on the new file
