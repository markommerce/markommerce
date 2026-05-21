# Task 006: Repository Contracts + In-Memory Fakes

**Status**: completed
**Depends on**: 003, 004, 005
**Retry count**: 0

## Description
Define the repository interface contracts for the three catalog entities, plus hand-written in-memory fake implementations used by service and controller unit tests.

## Context
- Interfaces in `packages/catalog/src/Contracts/`. Each extends `Marko\Database\Repository\RepositoryInterface`. Verify the exact method set by reading `vendor/marko/database/src/Repository/RepositoryInterface.php` — at time of planning it declares `find`, `findOrFail`, `findAll`, `findBy`, `findOneBy`, `existsBy`, `save`, `delete`, `insertBatch`. The in-memory fakes MUST implement EVERY method on this interface (PHP will fatal on an unimplemented abstract method), including `findOrFail` (throws `RepositoryException` when not found) and `insertBatch`. `find`/`findOneBy` return a `?Entity`; `findAll`/`findBy` return an `EntityCollection` — the fakes must return real `Marko\Database\Entity\EntityCollection` instances, not plain arrays.
- `ProductRepositoryInterface` — adds `findBySku(string $sku): ?Product`.
- `CategoryRepositoryInterface` — no extra methods beyond the base interface.
- `ProductCategoryAssignmentRepositoryInterface` — adds:
  - `findByCategory(int $categoryId): array` — all assignments for a category.
  - `findByProductAndCategory(int $productId, int $categoryId): ?ProductCategoryAssignment` — the single assignment row, or null (used for idempotent assign and for detach).
- Fakes in `packages/catalog/tests/Support/` (e.g. `FakeProductRepository`, `FakeCategoryRepository`, `FakeProductCategoryAssignmentRepository`). Each implements its interface with array-backed in-memory storage and assigns incrementing ids on `save()`. Reference the `FakeProductRepository` example in `.claude/testing.md`.
- Add the `Markommerce\Catalog\Tests\Support\` PSR-4 path under `autoload-dev` in `composer.json` if a subnamespace mapping is needed (the existing `Markommerce\Catalog\Tests\` → `tests/` mapping already covers it).
- All interfaces and fakes: `declare(strict_types=1);`, type all parameters and returns, no `final`.

## Requirements (Test Descriptions)
- [x] `it ProductRepositoryInterface extends the marko RepositoryInterface`
- [x] `it ProductRepositoryInterface declares a findBySku method`
- [x] `it ProductCategoryAssignmentRepositoryInterface declares findByCategory and findByProductAndCategory methods`
- [x] `it FakeProductRepository stores and finds a product by id`
- [x] `it FakeProductRepository finds a product by sku and returns null for an unknown sku`
- [x] `it FakeProductCategoryAssignmentRepository returns only assignments matching a given category`

## Acceptance Criteria
- All requirements have passing tests
- Fakes fully satisfy their interfaces (no abstract-method gaps)
- Code follows code standards

## Implementation Notes
- Created three repository interfaces in `packages/catalog/src/Contracts/`: `ProductRepositoryInterface`, `CategoryRepositoryInterface`, `ProductCategoryAssignmentRepositoryInterface`. Each extends `Marko\Database\Repository\RepositoryInterface`.
- Created three in-memory fakes in `packages/catalog/tests/Support/`: `FakeProductRepository`, `FakeCategoryRepository`, `FakeProductCategoryAssignmentRepository`. All implement every method from the base interface, returning `EntityCollection` instances for collection results and assigning incrementing ids on `save()`.
- Added `autoload-dev` section to root `composer.json` to map `Markommerce\Catalog\Tests\` to `packages/catalog/tests/` so the test namespace resolves from the monorepo root autoloader.
- Test file at `packages/catalog/tests/Unit/Contracts/RepositoryContractsTest.php`.
