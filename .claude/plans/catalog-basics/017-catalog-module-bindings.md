# Task 017: Wire up catalog module.php bindings

**Status**: completed
**Depends on**: 013, 014, 015, 016
**Retry count**: 0

## Description
Create `packages/catalog/module.php` and register every interface↔implementation binding the catalog module owns. Mirror the admin-auth precedent — minimal file, only bindings (and closure-based factories where needed).

## Context
- File location: `packages/catalog/module.php`
- Pattern reference: `marko/admin-auth/module.php`
- Bindings to register:
  - `CategoryRepositoryInterface::class => CategoryRepository::class`
  - `ProductRepositoryInterface::class => ProductRepository::class`
  - `CategoryServiceInterface::class => CategoryService::class`
  - `ProductServiceInterface::class => ProductService::class`
  - `ProductPriceServiceInterface::class => ProductPriceService::class`
  - `CategoryAssignmentServiceInterface::class => CategoryAssignmentService::class`
- No closure factories needed if every dependency is itself bound elsewhere. `MoneyFactoryInterface` and `CurrencyConfigInterface` are bound by `markommerce/money-moneyphp/module.php` (task 004) — catalog relies on those bindings being present in the application, not on rebinding them here. If they are not bound (e.g. an app using a different money driver), the failure surfaces at container resolution with a clear `marko/core` error.
- The file returns an array with a `'bindings'` key (admin-auth precedent).

## Requirements (Test Descriptions)
- [ ] `it returns an array with a bindings key from module.php`
- [ ] `it binds CategoryRepositoryInterface to CategoryRepository`
- [ ] `it binds ProductRepositoryInterface to ProductRepository`
- [ ] `it binds CategoryServiceInterface to CategoryService`
- [ ] `it binds ProductServiceInterface to ProductService`
- [ ] `it binds ProductPriceServiceInterface to ProductPriceService`
- [ ] `it binds CategoryAssignmentServiceInterface to CategoryAssignmentService`
- [ ] `it ensures every bound interface key exists as a real interface`
- [ ] `it ensures every bound implementation class exists and implements its bound interface`
- [ ] `it verifies that every constructor dependency of every bound concrete class is satisfiable by merging catalog's bindings with markommerce/money-moneyphp's bindings and the well-known Marko infrastructure types (ConnectionInterface, EntityMetadataFactory, EntityHydrator, EventDispatcherInterface, TransactionInterface, QueryBuilderFactoryInterface, RelationshipLoader). Catches "I forgot to bind X" before runtime.`

## Acceptance Criteria
- Module file is small (only bindings).
- Tests in `packages/catalog/tests/Unit/ModuleTest.php` load the file with `require` and assert the returned array shape. For each binding entry, assert `interface_exists($key)`, `class_exists($value)`, and that the class implements the interface (via `ReflectionClass::implementsInterface`).
- A separate test (or section of the same test) loads `packages/money-moneyphp/module.php` in addition, merges the bindings arrays, and verifies via reflection that every constructor parameter of every concrete class in catalog's bindings is either bound (in the merged map), is a well-known Marko-infrastructure type (the container resolves these automatically: `ConnectionInterface`, `EntityMetadataFactory`, `EntityHydrator`, `EventDispatcherInterface`, `TransactionInterface`, `QueryBuilderFactoryInterface`, `RelationshipLoader`), or is nullable. Whitelist the Marko-infrastructure list as a constant in the test. This catches missing bindings without booting the real container.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
