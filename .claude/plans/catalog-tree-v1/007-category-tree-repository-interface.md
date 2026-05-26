# Task 007: `CategoryTreeRepositoryInterface` + fake implementation

**Status**: completed
**Depends on**: 004, 002
**Retry count**: 0

## Description
Define the contract for tree persistence and provide an in-memory `FakeCategoryTreeRepository` used by unit tests. The interface MUST extend `Marko\Database\Repository\RepositoryInterface<CategoryTree>` — matching the existing `CategoryRepositoryInterface` pattern — and add only the *custom* methods (`findByCode`, `findDefault`). All base methods (`find`, `findAll`, `save`, `delete`, `findBy`, `findOneBy`, `existsBy`, `insertBatch`, `findOrFail`) are inherited from the base; the concrete repo (task 010) gets them for free from `Marko\Database\Repository\Repository`.

## Context
- Interface file: `packages/catalog/src/Contracts/CategoryTreeRepositoryInterface.php`
- Fake file: `packages/catalog/tests/Support/FakeCategoryTreeRepository.php`
- Pattern to mirror: existing `packages/catalog/src/Contracts/CategoryRepositoryInterface.php` and `packages/catalog/tests/Support/FakeCategoryRepository.php`
- Interface naming follows project rule: parameter name = camelCase of interface name minus `Interface` suffix
- Custom methods to declare on the interface (the only methods to add):
  - `findByCode(string $code): ?CategoryTree`
  - `findDefault(): CategoryTree` — throws `DefaultTreeMissingException`
- DO NOT redeclare `find`, `findAll`, `save`, `delete`, `findBy`, `findOneBy`, `existsBy`, `insertBatch`, `findOrFail` — these come from the base `RepositoryInterface<CategoryTree>` and re-declaring them with narrowed types (e.g. `find(int $id): ?CategoryTree`) violates LSP and will fail at PHP class-loading time.
- The interface PHPDoc must declare `@extends RepositoryInterface<CategoryTree>` to type the generic.
- Fake must implement the **full** base contract — copy the shape of `FakeCategoryRepository` (find, findOrFail, findAll returning `EntityCollection`, findBy, findOneBy, existsBy, save, delete, insertBatch) and then add `findByCode` and `findDefault`.
- `@throws` PHPDoc tags required wherever exceptions propagate
- Fake stores entities in an associative array keyed by id; assigns ids on first save when null

## Requirements (Test Descriptions)
- [ ] `interface extends Marko\Database\Repository\RepositoryInterface`
- [ ] `interface declares findByCode and findDefault methods (custom methods only — base methods inherited)`
- [ ] `fake stores a tree on save and returns it from find by id`
- [ ] `fake assigns an id when saving a tree with null id`
- [ ] `fake returns null from find when id does not exist`
- [ ] `fake returns a tree by code when present`
- [ ] `fake returns null from findByCode when code does not exist`
- [ ] `fake findDefault returns the default tree when one exists`
- [ ] `fake findDefault throws DefaultTreeMissingException when no default exists`
- [ ] `fake removes a tree on delete`
- [ ] `fake findAll returns all stored trees as an EntityCollection`

## Acceptance Criteria
- Interface file and fake file in correct locations
- Test file `packages/catalog/tests/Unit/Repositories/FakeCategoryTreeRepositoryTest.php`
- Interface extends `RepositoryInterface<CategoryTree>` via PHPDoc `@extends`
- Custom methods carry full `@throws` documentation
- Fake implements the full `RepositoryInterface` contract (signatures matching base verbatim)
- PHPStan level 8 clean

## Implementation Notes
- The signature of base methods is `find(int|string $id): ?Entity` — fakes return the narrowed `?CategoryTree` but the **method signature in the fake must stay `int|string $id` and `?Entity`** (PHP does not permit narrowing in this direction for interface methods, but covariant return narrowing is allowed for concrete return types — see `FakeCategoryRepository::find()` which uses `?Category` for covariance). Mirror that pattern.
