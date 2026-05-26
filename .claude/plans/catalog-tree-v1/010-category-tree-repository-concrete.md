# Task 010: `CategoryTreeRepository` concrete implementation (Postgres)

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Implement the concrete `CategoryTreeRepository` extending `Marko\Database\Repository\Repository`. Integration-tested against the real Postgres driver.

## Context
- Target file: `packages/catalog/src/Repositories/CategoryTreeRepository.php`
- Pattern to mirror: existing `packages/catalog/src/Repositories/CategoryRepository.php`
  - Extends `Marko\Database\Repository\Repository`
  - Declares `protected const string ENTITY_CLASS = CategoryTree::class;`
- `findByCode` and `findDefault` likely need explicit query methods (consult `Repository`'s query API: `findOneBy`, `where`, etc.)
- `findDefault()` MUST throw `DefaultTreeMissingException::forResolution()` when no row has `is_default = true`
- Integration test file: `packages/catalog/tests/Feature/Repositories/CategoryTreeRepositoryIntegrationTest.php`
- Use the same Postgres test infrastructure as `packages/config-pgsql/tests/Feature/PgsqlConfigStorageTest.php`

## Requirements (Test Descriptions)
- [ ] `it persists a tree and reads it back by id`
- [ ] `it finds a tree by its unique code`
- [ ] `it returns null when finding by an unknown code`
- [ ] `it finds the default tree when one exists`
- [ ] `it throws DefaultTreeMissingException when no default tree exists`
- [ ] `it deletes a tree`

## Acceptance Criteria
- Concrete class in correct location with `ENTITY_CLASS` const
- Integration test passes against real Postgres
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer during implementation)
