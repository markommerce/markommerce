# Task 020: Wire module bindings

**Status**: completed
**Depends on**: 010, 011, 012
**Retry count**: 0

## Description
Add the three new repository interface→implementation bindings to `packages/catalog/module.php`. Extend the existing `packages/catalog/tests/Unit/ModuleBindingsTest.php` with new cases asserting the three new bindings. The existing four cases must continue to pass.

## Context
- Modify existing file: `packages/catalog/module.php`
- Add three entries to the `bindings` array:
  - `CategoryTreeRepositoryInterface::class => CategoryTreeRepository::class`
  - `CategoryTreeNodeRepositoryInterface::class => CategoryTreeNodeRepository::class`
  - `CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class`
- Extend (do NOT recreate) the existing test file: `packages/catalog/tests/Unit/ModuleBindingsTest.php`
- If the integration test in task 021 reveals that `CategoryTreeService` or `CategoryService` cannot be auto-resolved by the Marko container, add concrete-class bindings here too:
  - `CategoryTreeService::class => CategoryTreeService::class`
  - `CategoryService::class => CategoryService::class`
  Confirm against existing services (`CategoryAssignmentService`, `ProductService`) — they currently appear NOT to be in `module.php`, suggesting the container auto-resolves concrete classes. If so, no extra bindings needed.

## Requirements (Test Descriptions)
- [ ] `module.php binds CategoryTreeRepositoryInterface to CategoryTreeRepository`
- [ ] `module.php binds CategoryTreeNodeRepositoryInterface to CategoryTreeNodeRepository`
- [ ] `module.php binds CategoryTreeMarketAssignmentRepositoryInterface to CategoryTreeMarketAssignmentRepository`
- [ ] `module.php preserves the existing pre-tree bindings (ProductRepositoryInterface, CategoryRepositoryInterface, ProductCategoryAssignmentRepositoryInterface)`

## Acceptance Criteria
- `module.php` updated with the three new bindings
- All four existing test cases in `ModuleBindingsTest.php` continue to pass unchanged
- The three new test cases pass
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer during implementation)
