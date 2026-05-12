# Task 014: Create CategoryServiceInterface and CategoryService

**Status**: completed
**Depends on**: 009, 010, 012
**Retry count**: 0

## Description
Add the category service — the public cross-module API for category CRUD. Owns validation and dispatches `CategoryCreated`, `CategoryUpdated`, `CategoryDeleted` events on the optional event dispatcher.

## Context
- Related files:
  - `packages/catalog/src/Service/CategoryServiceInterface.php`
  - `packages/catalog/src/Service/CategoryService.php`
- Methods on the interface (no `*OrFail` methods — by policy, throw-on-missing surface lives on the repository's inherited `findOrFail`):
  - `create(string $name): Category` — validates name is non-empty; throws `InvalidCategoryDataException::emptyName()` when invalid.
  - `get(int $id): ?Category` — returns null when not found. Callers needing throw-on-missing call `$categoryRepository->findOrFail($id)` directly.
  - `update(Category $category): Category` — validates name is non-empty (throws `InvalidCategoryDataException::emptyName()` otherwise), persists changes, dispatches `CategoryUpdated`
  - `delete(int $id): void` — throws `CategoryNotFoundException::forId($id)` if missing, dispatches `CategoryDeleted`. (Internal not-found check is translated to the catalog-specific exception so the catalog API surface throws catalog exceptions, never `RepositoryException`.)
  - `list(): array` — returns `array<Category>`. PHPDoc `@return array<Category>`.
- Constructor takes `CategoryRepositoryInterface $categoryRepository` and `?EventDispatcherInterface $eventDispatcher = null` (per admin-auth precedent).
- The `create` and `update` paths assert non-empty name and throw `InvalidCategoryDataException::emptyName()` (factory added in task 009).

## Requirements (Test Descriptions)
- [ ] `it creates a category with a valid name and returns the persisted entity`
- [ ] `it rejects a category with an empty name by throwing InvalidCategoryDataException`
- [ ] `it dispatches CategoryCreated after a successful create when a dispatcher is bound`
- [ ] `it returns the category from get when it exists`
- [ ] `it returns null from get when the category does not exist`
- [ ] `it updates an existing category and dispatches CategoryUpdated`
- [ ] `it deletes an existing category and dispatches CategoryDeleted`
- [ ] `it throws CategoryNotFoundException from delete when the category does not exist`
- [ ] `it lists all categories via the repository`
- [ ] `it works without an event dispatcher by skipping dispatch calls silently`

## Acceptance Criteria
- Tests use a hand-written `FakeCategoryRepository` implementing `CategoryRepositoryInterface` and a hand-written `RecordingEventDispatcher` — no PHPUnit mocks.
- The service is not `final`.
- `phpstan` clean at level 8.
- `@throws` PHPDoc on every method that can throw.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
