# Task 007: Repository Implementations

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create the concrete repository implementations for the three catalog entities, each extending `Marko\Database\Repository\Repository` and implementing its package interface.

## Context
- Create in `packages/catalog/src/Repositories/`:
  - `ProductRepository` — `ENTITY_CLASS = Product::class`, implements `ProductRepositoryInterface`. Implement `findBySku()` (the base `findOneBy(['sku' => $sku])` covers it).
  - `CategoryRepository` — `ENTITY_CLASS = Category::class`, implements `CategoryRepositoryInterface`.
  - `ProductCategoryAssignmentRepository` — `ENTITY_CLASS = ProductCategoryAssignment::class`, implements the assignment interface. Implement `findByCategory()` and `findByProductAndCategory()` via the base `findBy()` helper.
- The abstract `Repository` base supplies the constructor (`ConnectionInterface`, `EntityMetadataFactory`, `EntityHydrator`, optional query-builder factory / event dispatcher / relationship loader). Concrete classes only declare `protected const string ENTITY_CLASS` and the extra finder methods.
- The `Repository` base constructor signature is `(ConnectionInterface $connection, EntityMetadataFactory $metadataFactory, EntityHydrator $hydrator, ?QueryBuilderFactoryInterface = null, ?EventDispatcherInterface = null, ?RelationshipLoader = null)`. The catalog repositories only need the first three; the rest stay null.
- For tests, build repositories with a fake/logging `ConnectionInterface` — adapt the `makeLoggingConnection()` / `makeRepository()` helper pattern from `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php`. IMPORTANT: that reference helper calls `$metadataFactory->linkExtenders(Product::class, [...])` because the scope test uses a *companion* entity for overrides. Catalog entities use the `HasScopes` trait DIRECTLY (no companion — see tasks 003/004), so DO NOT copy the `linkExtenders` call; construct the `EntityMetadataFactory` plainly. Use the helper to assert SQL shape (table name, WHERE clause). Tag any test that needs a real database round-trip with `->group('integration-destructive')`.
- No `final`. `declare(strict_types=1);`. Add `@throws` tags for any method that propagates `RepositoryException`.

## Requirements (Test Descriptions)
- [x] `it ProductRepository declares Product as its entity class`
- [x] `it ProductRepository findBySku queries the catalog_products table filtered by sku`
- [x] `it ProductRepository findBySku returns null when no product matches`
- [x] `it CategoryRepository declares Category as its entity class`
- [x] `it ProductCategoryAssignmentRepository declares the assignment entity as its entity class`
- [x] `it ProductCategoryAssignmentRepository findByCategory queries assignments filtered by category id`

## Acceptance Criteria
- All requirements have passing tests
- Each repository satisfies its package interface
- Code follows code standards

## Implementation Notes
- Created `ProductRepository`, `CategoryRepository`, and `ProductCategoryAssignmentRepository` in `packages/catalog/src/Repositories/`.
- Each class extends `Marko\Database\Repository\Repository` and implements its corresponding contract interface.
- `ProductRepository::findBySku()` delegates to `findOneBy(['sku' => $sku])`.
- `ProductCategoryAssignmentRepository::findByCategory()` delegates to `findBy(['categoryId' => $categoryId])->toArray()`.
- `ProductCategoryAssignmentRepository::findByProductAndCategory()` delegates to `findOneBy(['productId' => $productId, 'categoryId' => $categoryId])`.
- Test helpers use `makeCatalogLoggingConnection()` and per-repository factory functions. `EntityMetadataFactory` is constructed plainly (no `linkExtenders`) since catalog entities use `HasScopes` directly rather than via companion classes.
- Tests use a query-logging connection to assert SQL table name and WHERE clause shape without a real database.
