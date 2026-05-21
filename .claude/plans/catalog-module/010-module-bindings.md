# Task 010: module.php Interface Bindings

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Populate `packages/catalog/module.php` so the catalog repository interfaces resolve to their concrete implementations through Marko's container.

## Context
- Edit `packages/catalog/module.php` (created as `return [];` in task 001).
- Return a `bindings` array mapping each repository interface to its concrete class:
  - `ProductRepositoryInterface::class => ProductRepository::class`
  - `CategoryRepositoryInterface::class => CategoryRepository::class`
  - `ProductCategoryAssignmentRepositoryInterface::class => ProductCategoryAssignmentRepository::class`
- Reference the `bindings` example in `.claude/architecture.md` (Module Registration) and `packages/scope/module.php`.
- Entities, routes, and seeders are auto-discovered by Marko's module system — do NOT register them in `module.php`.
- If the concrete repositories require constructor dependencies the container cannot autowire, use a closure-style binding as shown in `packages/scope/module.php`; otherwise a plain class-string mapping is sufficient.
- `declare(strict_types=1);`.

## Requirements (Test Descriptions)
- [x] `it module.php returns an array with a bindings key`
- [x] `it binds ProductRepositoryInterface to the concrete ProductRepository`
- [x] `it binds CategoryRepositoryInterface to the concrete CategoryRepository`
- [x] `it binds ProductCategoryAssignmentRepositoryInterface to the concrete assignment repository`

## Acceptance Criteria
- All requirements have passing tests
- `module.php` is a valid Marko module manifest
- Code follows code standards

## Implementation Notes
- Used plain class-string bindings (no closures needed) since all three repositories extend `Repository` and receive their dependencies via constructor injection that the container can autowire automatically.
- Test file created at `packages/catalog/tests/Unit/ModuleBindingsTest.php` with a shared `readCatalogModule()` helper that `require`s the module file and verifies its structure.
