# Task 002: Tree lifecycle exceptions

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create five exception classes covering tree-level lifecycle errors. Each extends `Marko\Core\Exceptions\MarkoException` directly (matching existing `CategoryNotFoundException` pattern) with named static factories carrying structured `context` and actionable `suggestion`.

## Context
- Target directory: `packages/catalog/src/Exceptions/`
- Namespace: `Markommerce\Catalog\Exceptions`
- Pattern to mirror: existing `packages/catalog/src/Exceptions/CategoryNotFoundException.php`
- All exceptions extend `MarkoException` directly — there is NO intermediate `CatalogException` class
- Each exception ships with a named static factory; do not provide a public constructor for callers
- Test pattern: assert message format, context, suggestion via the static factory

Classes to create (one file each):
1. `DefaultTreeMissingException` — `forResolution()` factory
2. `DuplicateDefaultTreeException` — `forCode(string $code)` factory
3. `CannotDeleteDefaultTreeException` — `forTreeId(int $treeId)` factory
4. `TreeHasMarketAssignmentsException` — `forTreeId(int $treeId, array $markets)` factory
5. `CategoryTreeNotFoundException` — `forId(int $id)` and `forCode(string $code)` factories

## Requirements (Test Descriptions)
- [ ] `DefaultTreeMissingException::forResolution returns instance with descriptive message, context, and suggestion`
- [ ] `DuplicateDefaultTreeException::forCode reports the conflicting code in message and context`
- [ ] `CannotDeleteDefaultTreeException::forTreeId reports the tree id and explains why deletion is blocked`
- [ ] `TreeHasMarketAssignmentsException::forTreeId reports the markets that still reference the tree`
- [ ] `CategoryTreeNotFoundException::forId reports the requested id`
- [ ] `CategoryTreeNotFoundException::forCode reports the requested code`

## Acceptance Criteria
- One test file per exception in `packages/catalog/tests/Unit/Exceptions/`
- All exceptions extend `Marko\Core\Exceptions\MarkoException`
- Suggestions are actionable (tell the developer what to do, not just what failed)
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer during implementation)
