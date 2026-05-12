# Task 016: Create CategoryAssignmentServiceInterface and CategoryAssignmentService

**Status**: completed
**Depends on**: 009, 010, 011, 012
**Retry count**: 0

## Description
Add the dedicated service for product↔category assignment. Owns the pivot mutations, validates both sides exist, dispatches `ProductAssignedToCategory` / `ProductRemovedFromCategory`, and exposes query helpers.

## Context
- Related files:
  - `packages/catalog/src/Service/CategoryAssignmentServiceInterface.php`
  - `packages/catalog/src/Service/CategoryAssignmentService.php`
- Methods on the interface:
  - `assign(int $productId, int $categoryId): void` — validation order: check product first; if missing throw `ProductNotFoundException::forId($productId)`. Then check category; if missing throw `CategoryNotFoundException::forId($categoryId)`. Idempotent when the assignment already exists (do not re-insert, do not re-dispatch event); dispatches `ProductAssignedToCategory` on first successful insert.
  - `unassign(int $productId, int $categoryId): void` — no-op when the assignment doesn't exist (do not throw, do not dispatch); dispatches `ProductRemovedFromCategory` on actual removal (detect via the `affected_rows` return of `ConnectionInterface::execute`).
  - `getCategoriesForProduct(int $productId): array` — `@return array<Category>`; throws `ProductNotFoundException::forId($productId)` if the product doesn't exist.
  - `getProductsInCategory(int $categoryId): array` — `@return array<Product>`; throws `CategoryNotFoundException::forId($categoryId)` if the category doesn't exist.
- Constructor: `ProductRepositoryInterface $productRepository`, `CategoryRepositoryInterface $categoryRepository`, `Marko\Database\Connection\ConnectionInterface $connection`, `Marko\Database\Connection\TransactionInterface $transaction`, `?EventDispatcherInterface $eventDispatcher = null`.
  - `ConnectionInterface` exposes `query()/execute()/lastInsertId()` only — transactional methods (`beginTransaction/commit/rollback/inTransaction`) live on the separate `TransactionInterface`. Concrete drivers implement both; in the binding container the same instance is bound to both interfaces, so the service receives the same underlying connection twice (once typed as Connection, once as Transaction).
- Direct pivot SQL is acceptable (matches `AdminUserRepository::syncRoles` precedent — line 64-77). The service runs the assignment insert/delete on `$connection->execute()`. Wrap any multi-statement path (e.g. SELECT-then-INSERT in `assign`) in `$transaction->beginTransaction()` / `commit()` / `rollback()` with try/catch.
- `assign` algorithm (idempotency-safe):
  1. `$transaction->beginTransaction()`
  2. `SELECT 1 FROM product_categories WHERE product_id = ? AND category_id = ? FOR UPDATE` via `$connection->query()`
  3. If a row exists: `$transaction->commit()` and return without dispatching.
  4. Otherwise: `INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)` via `$connection->execute()`, then `$transaction->commit()`, then dispatch `ProductAssignedToCategory`.
  5. On any exception: `$transaction->rollback()` and rethrow.
- For `getCategoriesForProduct` / `getProductsInCategory`, issue a dedicated SQL query via `$connection->query()` (the admin-auth `AdminUserRepository::getRolesForUser` is the template). The `with()` eager-loading API lives only on the concrete `Repository` (not on `RepositoryInterface`), so it cannot be used through the interface-typed dependencies here. The catalog repositories may grow `getCategoriesForProduct` / `getProductsInCategory` helpers in a later refactor — for this task the SQL lives in the service.
- **SQL flavor**: the raw SQL written by this service (including `SELECT ... FOR UPDATE` in the `assign` algorithm) is MySQL-flavored. Catalog currently targets MySQL via `marko/database-mysql`; portability to other drivers is out of scope for this plan. Column names in raw SQL use the snake_case names produced by the schema generator (`product_id`, `category_id`), not the camelCase property names on `ProductCategory`.
- For hydrating the result of the join queries back into `Category` / `Product` entities, follow the admin-auth precedent (`AdminUserRepository::getRolesForUser` uses `$this->metadataFactory->parse(Role::class)` + `$this->hydrator->hydrate(...)`) — but the service does not have access to those helpers directly. Instead, after fetching the raw category ids (or product ids) via the join, call `$categoryRepository->findBy(['id' => $ids])` (or `$productRepository->findBy(...)`) to load entities through the existing repository contract. This keeps hydration concerns on the repository layer.

## Requirements (Test Descriptions)
- [ ] `it assigns a product to a category and dispatches ProductAssignedToCategory`
- [ ] `it wraps the assign select-then-insert in a transaction and commits on success`
- [ ] `it rolls back the transaction in assign when the insert throws`
- [ ] `it is idempotent when the same assignment is created twice and does not redispatch the event`
- [ ] `it throws ProductNotFoundException from assign when the product does not exist before consulting the category`
- [ ] `it throws CategoryNotFoundException from assign when the product exists but the category does not`
- [ ] `it unassigns an existing assignment and dispatches ProductRemovedFromCategory only when execute reports a non-zero affected-row count`
- [ ] `it is a no-op when unassigning a non-existent assignment and does not dispatch`
- [ ] `it returns the categories for a product via getCategoriesForProduct`
- [ ] `it throws ProductNotFoundException from getCategoriesForProduct when the product does not exist`
- [ ] `it returns the products for a category via getProductsInCategory`
- [ ] `it throws CategoryNotFoundException from getProductsInCategory when the category does not exist`
- [ ] `it works without an event dispatcher by skipping dispatch calls silently`

## Acceptance Criteria
- Tests use hand-written `FakeProductRepository`, `FakeCategoryRepository`, a `FakeConnection` (implementing `ConnectionInterface` AND `TransactionInterface`) that records executed SQL + parameters and scripted SELECT results, and a `RecordingEventDispatcher`. Shared fakes live in `packages/catalog/tests/Support/` (registered via the `Markommerce\Catalog\Tests\` autoload-dev namespace from task 004). Real-database tests for the assignment SQL itself belong under the `integration-destructive` group and are out of scope for this task's unit tests.
- All assignment SQL is parameterized (no string interpolation of user-supplied values).
- Not `final`.
- `@throws` PHPDoc on every throwing method.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
