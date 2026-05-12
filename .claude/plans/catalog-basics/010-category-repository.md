# Task 010: Create CategoryRepositoryInterface and CategoryRepository

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Add the category repository interface and a concrete implementation extending `Marko\Database\Repository\Repository<Category>`. The interface re-exposes the base CRUD methods so consumers can depend on the contract; the concrete class only needs to set `ENTITY_CLASS` and inherit the rest.

## Context
- Related files:
  - `packages/catalog/src/Repository/CategoryRepositoryInterface.php`
  - `packages/catalog/src/Repository/CategoryRepository.php`
- Pattern reference: `marko/admin-auth/src/Repository/AdminUserRepository.php` and `AdminUserRepositoryInterface.php`.
- The interface should extend `Marko\Database\Repository\RepositoryInterface<Category>` (template parameter — phpstan-friendly) so callers get full typing. The parent interface guarantees `find/findOrFail/findAll/findBy/findOneBy/existsBy/save/delete/insertBatch`. Methods like `with`, `matching`, `count`, `exists` live only on the concrete `Repository` and are NOT part of the interface contract — services must not rely on them via the interface.
- The concrete extends `Marko\Database\Repository\Repository<Category>` with `protected const string ENTITY_CLASS = Category::class`. No further overrides needed unless category-specific helpers prove necessary; for now there are none.

## Requirements (Test Descriptions)
- [ ] `it declares CategoryRepositoryInterface extending RepositoryInterface generic over Category`
- [ ] `it implements CategoryRepositoryInterface in CategoryRepository`
- [ ] `it sets ENTITY_CLASS to the Category fully qualified class name`
- [ ] `it inherits base contract methods find findAll save delete findBy findOneBy existsBy from the parent interface`

## Acceptance Criteria
- The interface is the type other modules depend on — concrete is internal.
- Tests in `packages/catalog/tests/Unit/Repository/CategoryRepositoryTest.php`. Use reflection / phpstan-equivalent class-level assertions; do not require a database for this task's unit tests.
- Not `final`.
- `phpstan` clean at level 8 with correct generic type parameters.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
