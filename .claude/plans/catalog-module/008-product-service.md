# Task 008: ProductService (SKU Uniqueness)

**Status**: completed
**Depends on**: 002, 006
**Retry count**: 0

## Description
Create `ProductService` — the business-logic layer for creating and retrieving products. It enforces globally-unique SKUs by checking for an existing SKU before persisting and throwing `DuplicateSkuException` on collision.

## Context
- Create at `packages/catalog/src/Services/ProductService.php`.
- Constructor-injects `ProductRepositoryInterface` (parameter name `productRepository`, per the interface-parameter-naming rule).
- Methods:
  - `createProduct(string $sku, string $name, ?string $description = null): Product` — calls `findBySku()` first; if a product exists, throws `DuplicateSkuException::forSku()`; otherwise builds a `Product`, sets base properties, `save()`s it, and returns it. The `name`/`description` arguments set the entity's *base* (default-scope) property values directly.
  - `getProduct(int $id): Product` — returns the product or throws `ProductNotFoundException::forId()`.
- The unique DB index (task 004) is the second line of defence; this service is the first ("loud errors").
- **TOCTOU race:** the `findBySku()`-then-`save()` sequence is not atomic — a concurrent request can pass the pre-check and then collide on the DB unique index, where the `save()` raises a `Marko\Database\Exceptions\RepositoryException` (a unique-constraint violation surfaced by the driver). `createProduct()` MUST catch a `RepositoryException` thrown by `save()` and, when it represents a uniqueness violation, re-throw it as `DuplicateSkuException::forSku()` so callers always get the same loud, typed error regardless of which guard fired. Add the `DuplicateSkuException` `@throws` tag accordingly. (The in-memory fake in task 006 does not enforce uniqueness, so this path is exercised with a fake whose `save()` is configured to throw, or noted as integration-only — pick one and state it.)
- Tests use `FakeProductRepository` from task 006.
- `@throws` tags required on `createProduct` (`DuplicateSkuException`) and `getProduct` (`ProductNotFoundException`). No `final`. `declare(strict_types=1);`.

## Requirements (Test Descriptions)
- [x] `it creates a product with a unique sku`
- [x] `it persists the created product through the repository`
- [x] `it returns a Product carrying the given sku name and description`
- [x] `it throws DuplicateSkuException when creating a product whose sku already exists`
- [x] `it converts a repository uniqueness violation on save into a DuplicateSkuException`
- [x] `it retrieves an existing product by id`
- [x] `it throws ProductNotFoundException when retrieving a product id that does not exist`

## Acceptance Criteria
- All requirements have passing tests
- Service depends only on the repository interface, never a concrete class
- Code follows code standards

## Implementation Notes
- Created `packages/catalog/src/Services/ProductService.php` with `createProduct()` and `getProduct()` methods.
- `createProduct()` checks for existing SKU via `findBySku()`, then saves. Any `RepositoryException` thrown by `save()` is re-thrown as `DuplicateSkuException` to handle the TOCTOU race condition.
- The TOCTOU path is tested by an anonymous subclass of `FakeProductRepository` whose `save()` always throws a `RepositoryException`.
- `@throws DuplicateSkuException` on `createProduct()`, `@throws ProductNotFoundException` on `getProduct()`.
- No `final`, `declare(strict_types=1)`, constructor injection only.
