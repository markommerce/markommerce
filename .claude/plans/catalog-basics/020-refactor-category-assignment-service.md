# Task 020: Refactor CategoryAssignmentService to use ProductCategoryRepository

**Status**: completed
**Depends on**: 019
**Retry count**: 0

## Description
Replace the direct `ConnectionInterface` / `TransactionInterface` injection and raw SQL in `CategoryAssignmentService` with the new `ProductCategoryRepositoryInterface` (task 019). After this task lands, no catalog service references a database class directly — the architecture rule "Application code never touches database classes directly" holds end-to-end.

## Context

### `CategoryAssignmentService` rewrite

**New constructor signature:**
```php
public function __construct(
    private ProductRepositoryInterface $productRepository,
    private CategoryRepositoryInterface $categoryRepository,
    private ProductCategoryRepositoryInterface $productCategoryRepository,
    private ?EventDispatcherInterface $eventDispatcher = null,
) {}
```

`ConnectionInterface` and `TransactionInterface` imports are deleted.

**`assign(int $productId, int $categoryId): void`:**
1. `$this->productRepository->find($productId)` is null → throw `ProductNotFoundException::forId($productId)`
2. `$this->categoryRepository->find($categoryId)` is null → throw `CategoryNotFoundException::forId($categoryId)`
3. `$inserted = $this->productCategoryRepository->assign($productId, $categoryId)`
4. If `$inserted === true`: `$this->eventDispatcher?->dispatch(new ProductAssignedToCategory($productId, $categoryId))`

**`unassign(int $productId, int $categoryId): void`:**
1. `$removed = $this->productCategoryRepository->unassign($productId, $categoryId)`
2. If `$removed === true`: `$this->eventDispatcher?->dispatch(new ProductRemovedFromCategory($productId, $categoryId))`

(No product/category existence check on unassign — that's the same behaviour as the current implementation.)

**`getCategoriesForProduct(int $productId): array`:**
1. `$this->productRepository->find($productId)` is null → throw `ProductNotFoundException::forId($productId)`
2. `$ids = $this->productCategoryRepository->findCategoryIdsForProduct($productId)`
3. Return `[]` immediately when `$ids === []`
4. Otherwise return `$this->categoryRepository->findBy(['id' => $ids])->toArray()` (or whatever iteration `EntityCollection` exposes — check what task 014 ended up using)

**`getProductsInCategory(int $categoryId): array`:**
Symmetric — validates category existence, calls `findProductIdsForCategory`, hydrates via `$productRepository->findBy(['id' => $ids])`.

### `packages/catalog/tests/Support/FakeProductCategoryRepository.php`

New shared fake (other future catalog tests may reuse it). Public arrays so tests can pre-seed assignments and inspect what was changed:

```php
class FakeProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    /** @var array<array{0: int, 1: int}> */
    public array $assignments = [];

    public function assign(int $productId, int $categoryId): bool
    {
        foreach ($this->assignments as $pair) {
            if ($pair[0] === $productId && $pair[1] === $categoryId) {
                return false;
            }
        }
        $this->assignments[] = [$productId, $categoryId];
        return true;
    }

    public function unassign(int $productId, int $categoryId): bool
    {
        $before = count($this->assignments);
        $this->assignments = array_values(array_filter(
            $this->assignments,
            fn (array $p) => !($p[0] === $productId && $p[1] === $categoryId),
        ));
        return count($this->assignments) !== $before;
    }

    /** @return array<int> */
    public function findCategoryIdsForProduct(int $productId): array
    {
        return array_values(array_map(
            fn (array $p): int => $p[1],
            array_filter($this->assignments, fn (array $p) => $p[0] === $productId),
        ));
    }

    /** @return array<int> */
    public function findProductIdsForCategory(int $categoryId): array
    {
        return array_values(array_map(
            fn (array $p): int => $p[0],
            array_filter($this->assignments, fn (array $p) => $p[1] === $categoryId),
        ));
    }
}
```

### Test rewrite

`packages/catalog/tests/Unit/Service/CategoryAssignmentServiceTest.php` — replace `FakeConnection` setup with `FakeProductCategoryRepository`. The 13 test scenarios from task 016 stay structurally the same but become noticeably shorter because there's no SQL string matching or transaction-log inspection.

Notable changes:
- "wraps the assign select-then-insert in a transaction and commits on success" — drop this test. The transactional behaviour is now an internal concern of `ProductCategoryRepository` (covered by task 019). Replace with: `it dispatches ProductAssignedToCategory only when the repository reports a new insertion`.
- "rolls back the transaction in assign when the insert throws" — drop. Same reason; this is task 019's responsibility now.
- "is idempotent when the same assignment is created twice and does not redispatch the event" — keep, but assert via `FakeProductCategoryRepository::$assignments` (only one row) and `RecordingEventDispatcher::$events` (only one event).
- All other scenarios — keep.

### `module.php` update

`packages/catalog/module.php` gains one binding:
```php
ProductCategoryRepositoryInterface::class => ProductCategoryRepository::class,
```

`ModuleTest` (`packages/catalog/tests/Unit/ModuleTest.php`) adds a requirement-level test verifying that binding, and the constructor-dependency-satisfiability scan now sees `ProductCategoryRepositoryInterface` on `CategoryAssignmentService`'s constructor and `ProductCategoryRepository` on the bindings side. The bindings need to be self-consistent.

### What gets deleted
- The `ConnectionInterface` and `TransactionInterface` imports in `CategoryAssignmentService.php`
- The `private ConnectionInterface $connection` and `private TransactionInterface $transaction` constructor params
- Every `$this->connection->query(...)`, `$this->connection->execute(...)`, `$this->transaction->beginTransaction()`, etc. call inside the service
- The `FakeConnection` setup blocks in `CategoryAssignmentServiceTest.php` — but **not** `FakeConnection.php` itself (`ProductRepositoryTest` and `ProductCategoryRepositoryTest` still use it)

## Requirements (Test Descriptions)
- [ ] `it constructs CategoryAssignmentService with ProductCategoryRepositoryInterface instead of ConnectionInterface and TransactionInterface`
- [ ] `it assigns a product to a category by delegating to the pivot repository and dispatches ProductAssignedToCategory on a fresh insert`
- [ ] `it does not redispatch ProductAssignedToCategory when the pivot repository reports the assignment already exists`
- [ ] `it throws ProductNotFoundException from assign when the product does not exist before consulting the category`
- [ ] `it throws CategoryNotFoundException from assign when the product exists but the category does not`
- [ ] `it unassigns by delegating to the pivot repository and dispatches ProductRemovedFromCategory only when the repository reports a row was removed`
- [ ] `it is a no-op when unassigning a non-existent assignment and does not dispatch`
- [ ] `it returns the categories for a product by calling findCategoryIdsForProduct and hydrating via the category repository`
- [ ] `it returns an empty array from getCategoriesForProduct when the product has no assignments without calling the category repository`
- [ ] `it throws ProductNotFoundException from getCategoriesForProduct when the product does not exist`
- [ ] `it returns the products in a category by calling findProductIdsForCategory and hydrating via the product repository`
- [ ] `it throws CategoryNotFoundException from getProductsInCategory when the category does not exist`
- [ ] `it works without an event dispatcher by skipping dispatch calls silently`
- [ ] `it binds ProductCategoryRepositoryInterface to ProductCategoryRepository in catalog module.php`

## Acceptance Criteria
- Files modified:
  - `packages/catalog/src/Service/CategoryAssignmentService.php` — constructor + all four methods rewritten
  - `packages/catalog/module.php` — one binding added
  - `packages/catalog/tests/Unit/Service/CategoryAssignmentServiceTest.php` — fully rewritten to use the new fake
  - `packages/catalog/tests/Unit/ModuleTest.php` — one new binding assertion in the per-binding test, no other changes
- File created:
  - `packages/catalog/tests/Support/FakeProductCategoryRepository.php`
- No file imports `Marko\Database\Connection\ConnectionInterface` or `Marko\Database\Connection\TransactionInterface` from `packages/catalog/src/Service/` after this task — verify via grep.
- All 14 test scenarios pass.
- The wider `composer test` suite still passes (160+ tests).
- `phpstan` clean at level 8.
- Not `final`.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
