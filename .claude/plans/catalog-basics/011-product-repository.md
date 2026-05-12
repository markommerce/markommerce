# Task 011: Create ProductRepositoryInterface and ProductRepository

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Add the product repository interface and concrete implementation. Same pattern as the category repository, plus a `findBySku(string $sku): ?Product` helper that wraps `findOneBy(['sku' => $sku])`.

## Context
- Related files:
  - `packages/catalog/src/Repository/ProductRepositoryInterface.php`
  - `packages/catalog/src/Repository/ProductRepository.php`
- Pattern reference: `AdminUserRepository::findByEmail` (line 26).
- Interface extends `Marko\Database\Repository\RepositoryInterface<Product>` and adds:
  - `findBySku(string $sku): ?Product`
- The parent interface guarantees `find/findOrFail/findAll/findBy/findOneBy/existsBy/save/delete/insertBatch`. `with`/`matching`/`count`/`exists` live only on the concrete `Repository` and are NOT part of the interface contract — services must not rely on them via the interface.
- Concrete extends `Marko\Database\Repository\Repository<Product>` with `protected const string ENTITY_CLASS = Product::class` and implements `findBySku` by delegating to `findOneBy(['sku' => $sku])`.

## Requirements (Test Descriptions)
- [ ] `it declares ProductRepositoryInterface extending RepositoryInterface generic over Product`
- [ ] `it declares a findBySku method on ProductRepositoryInterface returning a nullable Product`
- [ ] `it implements ProductRepositoryInterface in ProductRepository`
- [ ] `it sets ENTITY_CLASS to the Product fully qualified class name`
- [ ] `it returns null from findBySku when no product matches`
- [ ] `it returns the matching product from findBySku when the sku exists`

## Acceptance Criteria

### Unit tests (no real database)
- The interface drives the contract; consumers depend on it.
- Behavioural tests for `findBySku` (`returns null`, `returns matching`) instantiate the concrete `ProductRepository` with a hand-written `FakeConnection` (in `packages/catalog/tests/Support/FakeConnection.php`) that implements `Marko\Database\Connection\ConnectionInterface` and `TransactionInterface`. The fake returns scripted rows from `query()` and records `execute()` calls — same pattern as `marko/admin-auth/tests/Unit/Repository/AdminUserRepositoryTest.php` (`createAdminUserMockConnection` helper) but written as a class rather than a closure factory. The fake is reused by tasks 013, 014, 016 — design it once here so later tasks can extend it.
- Tests in `packages/catalog/tests/Unit/Repository/ProductRepositoryTest.php`.
- `phpstan` clean at level 8 with correct generic type parameters.

### Integration test (real database, `integration-destructive` group) — lives at monorepo root, NOT in catalog
- **Schema integration test** at `tests/Feature/Schema/CatalogSchemaIntegrationTest.php` (the monorepo root `tests/` directory, not inside `packages/catalog/`). Catalog stays cleanly DB-driver-free; the test that needs a real connection lives at the root where `marko/database-mysql` is already part of the dev environment.
- Bootstrap: the root `phpunit.xml` testsuite already pulls in `marko/database-mysql` via the root composer require (or its require-dev). The test boots a real Marko app context against the test database and resolves the real `ConnectionInterface` binding provided by `marko/database-mysql`'s `module.php`.
- Test imports the catalog entities by FQCN (`Markommerce\Catalog\Entity\Product`, `Category`, `ProductCategory`) — autoload covers this.
- Tagged `integration-destructive` so it runs under `composer test:all` (which CI invokes).
- Runs `db:migrate` (or invokes the migrator directly via `Migrator::migrate()`) to materialize the schema from the three catalog entities.
- Asserts via `INFORMATION_SCHEMA` queries that:
  - `products` table exists with the expected columns (id, sku, name, base_price_amount) and types (bigint for base_price_amount, varchar(255) for sku/name).
  - `categories` table exists with the expected columns.
  - `product_categories` table exists with the expected columns, surrogate id PK, a unique composite index on (product_id, category_id), and two ON DELETE CASCADE foreign keys.
- This test catches missing `#[Index]` / `#[ForeignKey]` declarations on the entities — which is the whole point of the entity-driven schema approach.
- The test must clean up after itself (drop the three tables on teardown) so it can be re-run without manual DB reset.
- The root `phpunit.xml` must include `tests/` as a testsuite path (verify on implementation; add if missing).
- Catalog's `composer.json` does **not** require `marko/database-mysql`. Catalog stays free of any DB-driver coupling.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
