# Task 018: `CategoryService::delete()` with placement guard

**Status**: completed
**Depends on**: 003, 008
**Retry count**: 0

## Description
Introduce a new `CategoryService` class providing `delete(int $categoryId): void`. The method blocks deletion when the category has any node placement in any tree, throwing `CategoryHasPlacementsException`. When no placements exist, the category is removed via the repository.

## Context
- Target file: `packages/catalog/src/Services/CategoryService.php`
- Namespace: `Markommerce\Catalog\Services`
- Constructor injects:
  - `CategoryRepositoryInterface $categoryRepository` (existing interface, already in code — NOT created by this plan)
  - `CategoryTreeNodeRepositoryInterface $categoryTreeNodeRepository` (from task 008)
- Methods:
  - `delete(int $categoryId): void`
    - Throws `CategoryNotFoundException` (existing exception, already in code — NOT created by this plan) if category doesn't exist
    - Calls `categoryTreeNodeRepository->findByCategoryAcrossTrees($categoryId)`
    - If non-empty: throws `CategoryHasPlacementsException::forCategory($categoryId, count($placements))` (created in task 003)
    - Else: deletes the category via the repository
- **Intentional behaviour**: `ProductCategoryAssignment` rows referencing the deleted category are silently CASCADE-deleted by the DB FK (see `ProductCategoryAssignment::categoryId` `onDelete: 'CASCADE'`). This is by design for v1 — `CategoryService::delete()` does NOT check for or warn about product assignments. The placement guard is the only soft block; FK cascade handles the rest. Do not add an assignment-count check.
- Test file: `packages/catalog/tests/Unit/Services/CategoryServiceTest.php`
- Uses the existing `FakeCategoryRepository` from `packages/catalog/tests/Support/FakeCategoryRepository.php` and `FakeCategoryTreeNodeRepository` from task 008

## Requirements (Test Descriptions)
- [ ] `delete removes a category that has no placements`
- [ ] `delete throws CategoryNotFoundException when category id is unknown`
- [ ] `delete throws CategoryHasPlacementsException when the category is placed in any tree`
- [ ] `CategoryHasPlacementsException carries the placement count for diagnostics`

## Acceptance Criteria
- Service in correct location
- `@throws` PHPDoc tags present
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer during implementation)
