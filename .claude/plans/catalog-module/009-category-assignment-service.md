# Task 009: CategoryAssignmentService

**Status**: completed
**Depends on**: 002, 006
**Retry count**: 0

## Description
Create `CategoryAssignmentService` — the business logic for assigning a product to a category, detaching it, and listing all products assigned to a category.

## Context
- Create at `packages/catalog/src/Services/CategoryAssignmentService.php`.
- Constructor-injects `ProductRepositoryInterface`, `CategoryRepositoryInterface`, and `ProductCategoryAssignmentRepositoryInterface` (parameter names follow the interface-naming rule).
- Methods:
  - `assign(int $productId, int $categoryId): void` — verifies the product and category exist (throw `ProductNotFoundException` / `CategoryNotFoundException` otherwise); if `findByProductAndCategory()` already returns a row, do nothing (idempotent); otherwise create and `save()` a `ProductCategoryAssignment`.
  - `detach(int $productId, int $categoryId): void` — if `findByProductAndCategory()` returns a row, `delete()` it; if none exists, do nothing (no error).
  - `productsInCategory(int $categoryId): array` — throws `CategoryNotFoundException` if the category does not exist; otherwise returns the `Product` objects for every assignment in that category (load assignment rows via `findByCategory()`, then load each product via the product repository). Returns `list<Product>`. **Edge case:** if an assignment row references a `productId` that the product repository can no longer find (orphaned row — DB-level `ON DELETE CASCADE` is declared on the pivot in task 005, but it is not enforced by the in-memory fakes and may not be enforced by SQLite without `PRAGMA foreign_keys`), the service MUST skip the missing product rather than emit a `null` into the returned list. The return value is always a clean `list<Product>` with no holes.
- Tests use the fakes from task 006.
- `@throws` tags required where exceptions propagate. No `final`. `declare(strict_types=1);`.

## Requirements (Test Descriptions)
- [x] `it assigns a product to a category creating an assignment row`
- [x] `it does not create a duplicate assignment when the product is already in the category`
- [x] `it throws CategoryNotFoundException when assigning to a category that does not exist`
- [x] `it detaches a product from a category removing the assignment row`
- [x] `it does nothing when detaching a product that is not assigned to the category`
- [x] `it lists every product assigned to a category`
- [x] `it skips an assignment whose product no longer exists when listing a category`
- [x] `it throws CategoryNotFoundException when listing products for a category that does not exist`

## Acceptance Criteria
- All requirements have passing tests
- Service depends only on repository interfaces
- Code follows code standards

## Implementation Notes
- Service created at `packages/catalog/src/Services/CategoryAssignmentService.php`
- Injects all three repository interfaces using camelCase parameter names per convention
- `assign()` checks product then category existence before creating assignment; idempotent on duplicate
- `detach()` silently no-ops when no assignment row exists
- `productsInCategory()` skips orphaned assignment rows (missing product) rather than failing
- Tests at `packages/catalog/tests/Unit/Services/CategoryAssignmentServiceTest.php` use fakes from task 006
