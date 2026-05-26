# Task 009: `CategoryTreeMarketAssignmentRepositoryInterface` + fake implementation

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Define the contract for market-to-tree assignment persistence and provide an in-memory fake. The interface MUST extend `Marko\Database\Repository\RepositoryInterface<CategoryTreeMarketAssignment>` and only declare the *custom* methods. Market identifier is the primary key — at most one tree per market.

## Context
- Interface file: `packages/catalog/src/Contracts/CategoryTreeMarketAssignmentRepositoryInterface.php`
- Fake file: `packages/catalog/tests/Support/FakeCategoryTreeMarketAssignmentRepository.php`
- Pattern to mirror: existing `ProductCategoryAssignmentRepositoryInterface`
- Custom methods to declare (the only methods to add):
  - `findByMarket(string $market): ?CategoryTreeMarketAssignment`
  - `findByTree(int $treeId): list<CategoryTreeMarketAssignment>`
- DO NOT redeclare `find`, `findAll`, `findBy`, `findOneBy`, `existsBy`, `save`, `delete`, `findOrFail`, `insertBatch` — these come from the base. In particular, `findAll(): EntityCollection<CategoryTreeMarketAssignment>` is inherited from the base; callers that need a list can call `->toArray()` on the collection.
- The fake's storage is keyed by `market` string (the PK column)
- `save()` semantics: because `market` is a string PK rather than auto-increment integer, an entity carrying `market='us'` looks "not new" to the framework's default `isNew` heuristic. The fake's `save()` MUST simply assign-by-PK (`$this->byMarket[$entity->market] = $entity`) — this naturally implements upsert. Document this in the fake.
- The fake's `find(int|string $id)` accepts the market string as `$id` (since the PK is a string).

## Requirements (Test Descriptions)
- [ ] `interface extends Marko\Database\Repository\RepositoryInterface`
- [ ] `interface declares the two custom methods (findByMarket, findByTree)`
- [ ] `fake stores an assignment on save and returns it from findByMarket`
- [ ] `fake save replaces an existing assignment for the same market (upsert)`
- [ ] `fake findByMarket returns null when no assignment exists for the market`
- [ ] `fake findByTree returns all markets pointing to the given tree`
- [ ] `fake findAll returns every stored assignment as an EntityCollection`
- [ ] `fake find(string $marketId) finds an assignment by its market PK`
- [ ] `fake removes an assignment on delete`

## Acceptance Criteria
- Interface and fake in correct locations
- Interface extends `RepositoryInterface<CategoryTreeMarketAssignment>` via PHPDoc `@extends`
- Test file `packages/catalog/tests/Unit/Repositories/FakeCategoryTreeMarketAssignmentRepositoryTest.php`
- Upsert behaviour explicit in tests
- Fake implements full `RepositoryInterface` contract (signatures matching base verbatim, including `find(int|string $id): ?Entity`)
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer during implementation)
