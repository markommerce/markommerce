# Task 013: `CategoryTreeService` — tree CRUD and default management

**Status**: completed
**Depends on**: 007, 009, 002
**Retry count**: 0

## Description
Create the `CategoryTreeService` class with constructor injection of both `CategoryTreeRepositoryInterface` and `CategoryTreeMarketAssignmentRepositoryInterface`. Implement tree-lifecycle methods: `createTree`, `deleteTree`, `setDefaultTree`, `ensureDefaultTreeExists`. Default-tree invariant (exactly one default) is enforced at the service layer because Marko's `#[Index]` attribute may not support partial unique indexes natively.

## Context
- Target file: `packages/catalog/src/Services/CategoryTreeService.php`
- Namespace: `Markommerce\Catalog\Services`
- Constructor injects:
  - `CategoryTreeRepositoryInterface $categoryTreeRepository`
  - `CategoryTreeMarketAssignmentRepositoryInterface $categoryTreeMarketAssignmentRepository`
- Public methods to implement in this task:
  - `createTree(string $code, string $name, bool $isDefault = false): CategoryTree` — throws `\InvalidArgumentException` if `$code` is empty (trimmed). Throws `DuplicateDefaultTreeException` if `isDefault=true` and a default already exists. The unique constraint on `code` surfaces as a repository exception if a duplicate code is provided — let it propagate.
  - `deleteTree(int $treeId): void` — throws `CategoryTreeNotFoundException`, `CannotDeleteDefaultTreeException`, `TreeHasMarketAssignmentsException`
  - `setDefaultTree(int $treeId): void` — throws `CategoryTreeNotFoundException` if the target tree id is unknown; atomic swap: flips the previous default's `isDefault` to false and the new one to true (transactional; if Marko has no explicit transaction API exposed here, do the two writes in tight succession and document the limitation)
  - `ensureDefaultTreeExists(): CategoryTree` — idempotent; if a default exists, return it; otherwise create one with `code='default'`, `name='Default'`, `isDefault=true`
- Test file: `packages/catalog/tests/Unit/Services/CategoryTreeServiceTreeCrudTest.php`
- Tests use the fakes from tasks 007 and 009 (no real DB)
- All exception throws must carry `@throws` PHPDoc tags

## Requirements (Test Descriptions)
- [x] `createTree persists a new tree with provided code and name`
- [x] `createTree throws InvalidArgumentException when code is empty or only whitespace`
- [x] `createTree with isDefault=true marks the tree as default`
- [x] `createTree with isDefault=true throws DuplicateDefaultTreeException when a default already exists`
- [x] `setDefaultTree promotes the given tree to default and demotes the previous default in one operation`
- [x] `setDefaultTree leaves exactly one default tree after the swap (no overlap, no gap)`
- [x] `setDefaultTree throws CategoryTreeNotFoundException when tree id is unknown`
- [x] `deleteTree removes a non-default tree without market assignments`
- [x] `deleteTree throws CategoryTreeNotFoundException when tree id is unknown`
- [x] `deleteTree throws CannotDeleteDefaultTreeException when targeting the default tree`
- [x] `deleteTree throws TreeHasMarketAssignmentsException when the tree still serves any market`
- [x] `ensureDefaultTreeExists returns existing default when present`
- [x] `ensureDefaultTreeExists creates a default tree when none exists`

## Acceptance Criteria
- Service class created with both repository dependencies injected
- All listed exceptions thrown with structured context where appropriate
- All `@throws` PHPDoc tags present
- PHPStan level 8 clean

## Implementation Notes
- Document the chosen approach to the default-tree invariant (service-level vs DB-level partial unique index) in code comments
- The `CategoryTreeService` constructor grows across tasks 013→015. To keep test files stable, introduce a `tests/Support/CategoryTreeServiceTestFactory.php` helper (or a Pest `beforeEach` in a shared trait) that constructs the service with all *currently-required* fakes wired. Subsequent tasks (014, 015, 016, 017) extend this helper as new dependencies are added.
