# Task 019: Create ProductCategoryRepository (extract pivot SQL out of the service)

**Status**: completed
**Depends on**: 007, 011
**Retry count**: 0

## Description
Introduce a dedicated repository for the `product_categories` pivot so that the SELECT/INSERT/DELETE SQL currently sitting in `CategoryAssignmentService` (task 016) moves into the data-access layer where it belongs. This task creates the contract + concrete only. Task 020 rewrites the service to consume it.

## Why this task exists
Code review flagged that `CategoryAssignmentService` directly injects `ConnectionInterface` and `TransactionInterface` and executes raw SQL — a violation of `.claude/architecture.md`:

> "Data access is always behind a `*RepositoryInterface`. Concrete repositories live in the implementing module. **Application code never touches database classes directly.**"
>
> "Business logic lives in services, not in repositories or controllers. Services depend on repository interfaces, never on concrete implementations."

`AdminUserRepository::syncRoles` (the precedent we modeled the SQL on) keeps the SQL inside the repository. Task 016 accidentally copied the SQL style but skipped the encapsulation layer. This task closes that gap.

## Context

### `packages/catalog/src/Repository/ProductCategoryRepositoryInterface.php`

Standalone interface — does **not** extend `Marko\Database\Repository\RepositoryInterface`. The pivot is accessed exclusively through these four custom methods; surfacing the generic CRUD (`find`/`findBy`/`save`/`delete`) on the pivot would muddy the contract for consumers who only care about assign/unassign semantics.

```php
interface ProductCategoryRepositoryInterface
{
    /**
     * Idempotently assign a product to a category.
     *
     * @return bool true when a new row was inserted; false when the assignment already existed.
     */
    public function assign(int $productId, int $categoryId): bool;

    /**
     * Remove a product↔category assignment.
     *
     * @return bool true when a row was deleted; false when no assignment existed (no-op).
     */
    public function unassign(int $productId, int $categoryId): bool;

    /** @return array<int> */
    public function findCategoryIdsForProduct(int $productId): array;

    /** @return array<int> */
    public function findProductIdsForCategory(int $categoryId): array;
}
```

The bool return values are deliberately the "did something happen?" signal — the service uses them to decide whether to dispatch an event. This pushes the idempotency knowledge into the repository (where it can be enforced atomically) and gives the service a clean predicate to drive events.

### `packages/catalog/src/Repository/ProductCategoryRepository.php`

Extends `Marko\Database\Repository\Repository<ProductCategory>`. The base class already provides `protected readonly ConnectionInterface $connection` — we use it, but **only here, inside the data-access layer**. The base also exposes the `TransactionInterface` capability via the same connection instance (the marko-database connection drivers implement both interfaces; see `Marko\Database\Repository\Repository::insertBatch` for the precedent that casts via `instanceof TransactionInterface`).

```php
/**
 * @extends Repository<ProductCategory>
 */
class ProductCategoryRepository extends Repository implements ProductCategoryRepositoryInterface
{
    protected const string ENTITY_CLASS = ProductCategory::class;

    public function assign(int $productId, int $categoryId): bool { /* see algorithm below */ }
    public function unassign(int $productId, int $categoryId): bool { /* see below */ }
    public function findCategoryIdsForProduct(int $productId): array { /* see below */ }
    public function findProductIdsForCategory(int $categoryId): array { /* see below */ }
}
```

**`assign` algorithm (idempotency-safe, transactional):**
1. If `$this->connection instanceof TransactionInterface`: `$this->connection->beginTransaction()`
2. `SELECT 1 FROM product_categories WHERE product_id = ? AND category_id = ? FOR UPDATE` via `$this->connection->query()` with `[$productId, $categoryId]`
3. If a row is returned: commit (if owned) and `return false`
4. Otherwise: `INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)`; commit (if owned); `return true`
5. On any throwable: rollback (if owned) and rethrow

The transaction guards against a concurrent assign for the same pair — the `FOR UPDATE` lock ensures one of the racers sees the row and short-circuits.

**`unassign` algorithm:**
1. `$affected = $this->connection->execute('DELETE FROM product_categories WHERE product_id = ? AND category_id = ?', [$productId, $categoryId])`
2. `return $affected > 0`

**`findCategoryIdsForProduct` / `findProductIdsForCategory`:**
- Simple `SELECT category_id FROM product_categories WHERE product_id = ?` (or symmetric)
- Map rows to `int[]`: `array_map(fn (array $r): int => (int) $r['category_id'], $rows)`
- Return empty array when no rows match — do **not** throw "not found" here; the service decides whether the parent entity must exist.

### SQL flavor
Same caveat as task 016: SQL is MySQL-flavored (`SELECT ... FOR UPDATE`). Cross-driver portability is out of scope for this plan.

## Requirements (Test Descriptions)
- [ ] `it declares ProductCategoryRepositoryInterface with assign unassign findCategoryIdsForProduct and findProductIdsForCategory methods returning the correct types`
- [ ] `it does not extend RepositoryInterface so consumers only see pivot-specific methods`
- [ ] `it implements ProductCategoryRepositoryInterface in ProductCategoryRepository`
- [ ] `it sets ENTITY_CLASS to the ProductCategory fully qualified class name`
- [ ] `it returns true from assign when inserting a new pivot row and false when the row already exists`
- [ ] `it dispatches the SELECT FOR UPDATE before the INSERT inside a transaction in assign`
- [ ] `it rolls back the transaction and rethrows when the insert in assign fails`
- [ ] `it returns true from unassign when the DELETE affects a row and false when no rows are affected`
- [ ] `it returns an array of integer category ids from findCategoryIdsForProduct in order from the underlying query`
- [ ] `it returns an empty array from findCategoryIdsForProduct when the product has no assignments`
- [ ] `it returns an array of integer product ids from findProductIdsForCategory`
- [ ] `it parameterizes every SQL statement (no string interpolation of user-supplied values)`

## Acceptance Criteria
- Files:
  - `packages/catalog/src/Repository/ProductCategoryRepositoryInterface.php`
  - `packages/catalog/src/Repository/ProductCategoryRepository.php`
- Tests in `packages/catalog/tests/Unit/Repository/ProductCategoryRepositoryTest.php`.
- Tests reuse the existing `FakeConnection` from `packages/catalog/tests/Support/FakeConnection.php` (created in task 011, extended in task 016 with `executeReturnValue` / `throwOnExecute` / `transactionLog`). No new fakes needed in this task.
- Instantiate the concrete `ProductCategoryRepository` directly with `FakeConnection` + the real `EntityMetadataFactory` and `EntityHydrator` — same pattern as `ProductRepositoryTest` (task 011).
- `@throws` PHPDoc on `assign` (the transaction path can rethrow).
- Not `final`.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
