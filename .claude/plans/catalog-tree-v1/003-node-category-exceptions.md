# Task 003: Node & category exceptions

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create four exception classes covering node-level operations and category deletion. Each extends `Marko\Core\Exceptions\MarkoException` directly with named static factories carrying structured `context` and actionable `suggestion`.

## Context
- Target directory: `packages/catalog/src/Exceptions/`
- Namespace: `Markommerce\Catalog\Exceptions`
- Pattern to mirror: existing `CategoryNotFoundException`
- All exceptions extend `MarkoException` directly (no intermediate `CatalogException`)

Classes to create (one file each):
1. `CircularNodeReferenceException` — `forNodeAndParent(int $nodeId, int $proposedParentId)` factory
2. `NodeNotInTreeException` — two factories:
   - `forNodeAndTree(int $nodeId, int $expectedTreeId, int $actualTreeId)` — when a node is found but in the wrong tree
   - `forParentMismatch(int $nodeId, ?int $expectedParentNodeId, ?int $actualParentNodeId)` — when a node is in the right tree but has a different parent than the operation expects (used by `reorderSiblings` in task 016)
3. `CategoryHasPlacementsException` — `forCategory(int $categoryId, int $placementCount)` factory
4. `CategoryTreeNodeNotFoundException` — `forId(int $id)` factory

## Requirements (Test Descriptions)
- [ ] `CircularNodeReferenceException::forNodeAndParent reports both node ids and explains the cycle`
- [ ] `NodeNotInTreeException::forNodeAndTree reports expected vs actual tree id`
- [ ] `NodeNotInTreeException::forParentMismatch reports expected vs actual parent id`
- [ ] `CategoryHasPlacementsException::forCategory reports category id and placement count`
- [ ] `CategoryTreeNodeNotFoundException::forId reports the requested id`
- [ ] `each exception extends MarkoException and exposes message, context, and suggestion`

## Acceptance Criteria
- One test file per exception in `packages/catalog/tests/Unit/Exceptions/`
- Suggestions describe a remediation path (e.g. "place node under a different parent")
- Both factories on `NodeNotInTreeException` produce distinct messages and contexts so callers can debug which validation failed
- PHPStan level 8 clean

## Implementation Notes
- `forParentMismatch` parameters are nullable because `parent_node_id` is nullable (a root node has `parentNodeId === null`). The suggestion should hint at "ensure all reordered nodes share the same parent, or fetch them via `findChildren(parentId, treeId)` before calling `reorderSiblings`".
