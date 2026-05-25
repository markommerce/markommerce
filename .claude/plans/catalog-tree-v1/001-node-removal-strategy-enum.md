# Task 001: `NodeRemovalStrategy` enum

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `NodeRemovalStrategy` enum used by `CategoryTreeService::removeNode()` to require the caller to explicitly choose between cascading subtree deletion and promoting children. No default value — callers must name the strategy.

## Context
- Target file: `packages/catalog/src/Enum/NodeRemovalStrategy.php`
- Namespace: `Markommerce\Catalog\Enum`
- Pure value type; no dependencies on other catalog classes
- Project standard requires `declare(strict_types=1);` at top of every PHP file
- Backed or pure enum? Pure (no string/int backing) — these are behaviour selectors, not persisted values

## Requirements (Test Descriptions)
- [ ] `it defines a CASCADE case`
- [ ] `it defines a PROMOTE_CHILDREN case`
- [ ] `it has exactly two cases`
- [ ] `it is a pure enum without a backing type`

## Acceptance Criteria
- All requirements have passing tests in `packages/catalog/tests/Unit/Enum/NodeRemovalStrategyTest.php`
- File follows project coding standards (strict types, no final, no traits, etc.)
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer during implementation)
