# Task 012: `CategoryTreeMarketAssignmentRepository` concrete implementation (Postgres)

**Status**: completed
**Depends on**: 009
**Retry count**: 0

## Description
Implement the concrete `CategoryTreeMarketAssignmentRepository`. The entity uses a string PK (`market`) rather than auto-increment integer — `Marko\Database\Repository\Repository::save()` calls `EntityHydrator::isNew()` to choose between insert/update. For string-PK entities the default heuristic may not behave correctly (the PK is non-null from the moment the entity is constructed). This task MUST verify the behaviour against `EntityHydrator::isNew()` and override `save()` if needed.

## Context
- Target file: `packages/catalog/src/Repositories/CategoryTreeMarketAssignmentRepository.php`
- Extends `Marko\Database\Repository\Repository` with `ENTITY_CLASS = CategoryTreeMarketAssignment::class`
- The `save` method MUST implement **explicit upsert semantics** keyed by `market` (PK). Two acceptable strategies:
  1. Override `save()` to: look up by `market` via `findOneBy(['market' => $entity->market])`; if found, run UPDATE; otherwise INSERT.
  2. Use Postgres `INSERT INTO … ON CONFLICT (market) DO UPDATE SET tree_id = EXCLUDED.tree_id` (Postgres is the only driver in scope).
- The chosen strategy must be wrapped in a transaction (or be a single atomic SQL statement).
- Add a custom `findByMarket(string $market): ?CategoryTreeMarketAssignment` method (delegates to `findOneBy(['market' => $market])` or equivalent).
- Add `findByTree(int $treeId): list<CategoryTreeMarketAssignment>` (delegates to `findBy(['treeId' => $treeId])->toArray()`).
- Integration test: `packages/catalog/tests/Feature/Repositories/CategoryTreeMarketAssignmentRepositoryIntegrationTest.php`

## Requirements (Test Descriptions)
- [ ] `it persists a new assignment and reads it back by market`
- [ ] `it returns null when finding by a market with no assignment`
- [ ] `it updates an existing assignment when saving for the same market (upsert)`
- [ ] `it does not produce a duplicate-key error when saving twice for the same market`
- [ ] `it finds all assignments pointing to a given tree`
- [ ] `it returns the full list of assignments via findAll`
- [ ] `it deletes an assignment`

## Acceptance Criteria
- Concrete class with `ENTITY_CLASS` const
- Upsert behaviour verified (no duplicate-key error)
- Integration test passes against real Postgres
- The chosen upsert strategy documented inline in a class comment
- PHPStan level 8 clean

## Implementation Notes
- Before implementing, read `Marko\Database\Entity\EntityHydrator::isNew()` to understand the framework's "new vs existing" heuristic for non-auto-increment PKs. If the default heuristic incorrectly identifies a saved-then-mutated entity as "new", document the limitation and override `save()`.
- The override pattern: do not call `parent::save()`. Instead, branch on `findOneBy(['market' => $entity->market]) !== null`:
  - exists → build and execute UPDATE statement manually (mirror `Repository::update()` style)
  - not exists → build and execute INSERT (mirror `Repository::insert()` style)
- Document which strategy was chosen and why in the class-level docblock.
