# Plan: Tree-based catalog v1

## Created
2026-05-25

## Status
completed

## Objective
Introduce per-market category trees with shared category identity in `markommerce/catalog`. Categories remain identity entities; their placement in a navigation tree becomes a separate concern. Markets can share a default tree or have their own. Same category may appear at multiple positions within a tree.

## Related Issues
none

## Discovery Notes

Existing catalog state:
- `Category` entity is identity-only (`id`, `name` locale-scoped, `description` locale-scoped). No `parent_id`. Good — no removal needed.
- `Product`, `ProductCategoryAssignment`, repositories, `CategoryAssignmentService`, `ProductService`, `CategoryController`, `CatalogSeeder` all exist.
- Exceptions extend `Marko\Core\Exceptions\MarkoException` directly (e.g. `CategoryNotFoundException`). No intermediate `CatalogException` base class is in use. **The scope's mention of `CatalogException` was incorrect; new exceptions extend `MarkoException` directly.**
- Repositories extend `Marko\Database\Repository\Repository` with a `protected const string ENTITY_CLASS = …` line.
- FK references use `#[Column(name: 'foo_id', references: 'table_name', onDelete: 'CASCADE')]`.
- **No migration files** — Marko uses auto-migration from entity attributes (`#[Table]`, `#[Column]`, `#[Index]`). Database schema follows entity definitions automatically.
- The catalog seeder calls `insertBatch`, `findAll`, etc.; runs via `Marko\Database\Seed\Seeder` and the `#[Seeder(name: 'catalog')]` attribute.

### Key assumptions resolved during scoping

1. Multi-placement (same category at multiple positions in one tree) supported from v1.
2. `removeNode` takes an explicit `NodeRemovalStrategy` enum (`CASCADE` / `PROMOTE_CHILDREN`) — no default.
3. Category deletion blocks when placed (force unplacement first).
4. Trees have a stable `code` column.
5. Market identifiers are `varchar(64)`.
6. Materialized tree cache is in-request only (memoised in service).
7. Default tree is bootstrapped via `CategoryTreeService::ensureDefaultTreeExists()` (idempotent) — called by `CatalogSeeder` and explicitly by integration tests. No install-time hook is wired in this slice (no Marko install hook has been identified). Replaces the "migration seeds default tree" requirement since Marko has no migration files.
8. Category deletion guard lives in a new `CategoryService::delete()` method (the catalog has no existing category-lifecycle service).
9. The partial unique index `WHERE is_default = true` may not be directly expressible via `#[Index]`; if not, the invariant is enforced at the service layer with a transaction (and a regular unique index where possible).

## Scope

### In Scope

- New entities: `CategoryTree`, `CategoryTreeNode`, `CategoryTreeMarketAssignment`
- New enum: `NodeRemovalStrategy`
- New repository interfaces + concrete implementations for the three entities
- New `CategoryTreeService` covering: create/delete tree, set default, market assign/unassign, market→tree resolution, place category, move node (cycle detection), remove node (CASCADE/PROMOTE_CHILDREN), reorder siblings, materialized tree (in-request memoised)
- New `CategoryService::delete()` blocking when category is placed
- 9 new exception classes (extending `MarkoException` directly)
- `CatalogSeeder` updated to ensure default tree exists and place all seeded categories in it
- Module bindings in `packages/catalog/module.php`
- End-to-end feature test exercising the full lifecycle

### Out of Scope

- URL rewrites and slug fields
- Tree inheritance / diff mode
- Tree drafts / versioning
- Scoping `ProductCategoryAssignment` per market (assignments stay global)
- `Category::visible`, status, slug
- Per-market price, stock
- Admin UI
- "Primary category for URL" flag
- Persistent (cross-request) cache layer for materialized trees

## Success Criteria

- [ ] All new entities exist with correct table attributes and FK declarations
- [ ] All repositories implemented and contract-tested against fakes and the real Postgres driver
- [ ] `CategoryTreeService` covers every documented method + every documented failure mode
- [ ] `CategoryService::delete()` blocks when category is placed in any tree
- [ ] Cycle detection in `moveNode` is verified
- [ ] `NodeRemovalStrategy::CASCADE` and `PROMOTE_CHILDREN` behave as specified
- [ ] Default tree invariant enforced (cannot delete default; cannot create second default)
- [ ] Market-to-tree resolution falls back to default when no assignment
- [ ] `CatalogSeeder` produces a default tree with all seeded categories placed in it
- [ ] Module bindings expose all three new repositories
- [ ] End-to-end feature test passes against real Postgres
- [ ] All tests pass (parallel)
- [ ] PHPStan level 8 clean
- [ ] PHP-CS-Fixer / PHPCS clean
- [ ] Coverage ≥ 80%

## Task Overview

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | `NodeRemovalStrategy` enum | none | completed |
| 002 | Tree lifecycle exceptions (5 classes) | none | completed |
| 003 | Node & category exceptions (4 classes) | none | completed |
| 004 | `CategoryTree` entity | none | completed |
| 005 | `CategoryTreeNode` entity | 004 | completed |
| 006 | `CategoryTreeMarketAssignment` entity | 004 | completed |
| 007 | `CategoryTreeRepositoryInterface` + fake | 004, 002 | completed |
| 008 | `CategoryTreeNodeRepositoryInterface` + fake | 005 | completed |
| 009 | `CategoryTreeMarketAssignmentRepositoryInterface` + fake | 006 | completed |
| 010 | `CategoryTreeRepository` concrete (Postgres) | 007 | completed |
| 011 | `CategoryTreeNodeRepository` concrete (Postgres) | 008 | completed |
| 012 | `CategoryTreeMarketAssignmentRepository` concrete (Postgres) | 009 | completed |
| 013 | `CategoryTreeService` — tree CRUD + default management | 007, 009, 002 | completed |
| 014 | `CategoryTreeService` — market assignment + resolution | 013 | completed |
| 015 | `CategoryTreeService` — node placement + move (cycle detection) | 014, 008, 003 | completed |
| 016 | `CategoryTreeService` — node removal + reorder | 015, 001 | completed |
| 017 | `CategoryTreeService` — materialized tree with memoisation | 016 | completed |
| 018 | `CategoryService::delete()` with placement guard | 003, 008 | completed |
| 019 | Update `CatalogSeeder` to populate default tree | 017, 018 | completed |
| 020 | Wire module bindings | 010, 011, 012 | completed |
| 021 | End-to-end feature test | 020, 017, 019 | completed |

## Architecture Notes

- All entities use `marko/database` attributes — schema is auto-derived. No migration files.
- Exceptions extend `MarkoException` directly, named static factories (e.g. `forId`, `forCode`), populated with structured `context` and actionable `suggestion`.
- Repositories follow the existing `Repository` base class pattern with `ENTITY_CLASS` const.
- Repository **interfaces** extend `Marko\Database\Repository\RepositoryInterface<EntityClass>` (via PHPDoc `@extends`) and only declare *custom* methods. Base methods (`find`, `findAll`, `save`, `delete`, `findBy`, `findOneBy`, `existsBy`, `insertBatch`, `findOrFail`) are inherited — re-declaring them with narrowed types violates LSP and crashes PHP at class load. This mirrors the existing `CategoryRepositoryInterface` pattern.
- Fake repositories implement the full base contract verbatim (signatures matching `RepositoryInterface`, e.g. `find(int|string $id): ?Entity`) and use covariant return types for narrowed concrete returns where supported. Mirror `FakeCategoryRepository`.
- `CategoryTreeService` is the public-facing API; repositories are infrastructure. Tests for service use fake repositories; integration tests use real Postgres.
- The service grows method-by-method across tasks 013→017 to keep TDD cycles small. Tasks for the same class serialize their edits via dependencies (no parallel edits to the same file). A shared `tests/Support/CategoryTreeServiceTestFactory.php` helper keeps existing tests passing as the constructor grows.
- In-request memoisation lives inside the service (private `array $materializedTreeCache = []` keyed by `tree_id`). Invalidate on any structural change (place/move/remove/reorder). Mutators that don't receive `tree_id` directly (`moveNode`, `removeNode`) MUST load the affected node first to capture its `treeId` before invalidating.
- Multi-placement is permitted by design: `parent_node_id` references nodes (not categories). The unique index `(tree_id, parent_node_id, position)` prevents collisions at a given position only, not category duplication. On Postgres, NULL `parent_node_id` values are treated as distinct in the unique index — the service auto-assigns unique positions for root nodes via the `placeCategory` auto-position logic.
- `placeCategory` accepts an optional `?int $position = null`; when null, the service computes `max(siblings.position) + POSITION_GAP`. `POSITION_GAP` is a shared class constant (default 10) used by both `placeCategory` and `reorderSiblings`.
- `CategoryTreeMarketAssignment` has a string PK (`market`). The default `Repository::save()` path may not correctly upsert; task 012 explicitly overrides `save()` (or uses Postgres `ON CONFLICT`).

## Risks & Mitigations

- **Risk**: Partial unique index `WHERE is_default = true` may not be expressible via Marko's `#[Index]` attribute.
  - **Mitigation**: Service-level enforcement (transactional check-before-insert in `setDefaultTree` and `createTree(isDefault:true)`). Document the chosen approach in the implementation notes of task 013.
- **Risk**: Repository for `CategoryTreeMarketAssignment` uses `market` (varchar) as PK rather than auto-increment id. The `Repository` base may assume an integer auto-increment id.
  - **Mitigation**: Task 012 must verify behaviour against `Marko\Database\Repository\Repository`; if incompatible, override the necessary methods (probably `save` / `find` semantics).
- **Risk**: Cycle detection in `moveNode` could deadlock with concurrent moves.
  - **Mitigation**: Read the parent chain via single transaction; rely on existing FK referential integrity for correctness; acceptable for v1 single-admin workflows.
- **Risk**: Auto-migration may not create the FK constraints in the right order if entities are discovered out-of-order.
  - **Mitigation**: This is a Marko concern, not a markommerce concern; auto-migration handles ordering already (proven by other packages).
- **Risk**: Seeder rerun while a default tree already exists must be idempotent.
  - **Mitigation**: `ensureDefaultTreeExists()` is idempotent; node placement during seeding checks for existing placements before creating.
- **Risk**: `Category::visible` is out-of-scope but a category without any tree placement effectively becomes invisible; existing tests/features may rely on "all categories visible by default".
  - **Mitigation**: Task 019 places all seeded categories in default tree, preserving existing storefront behaviour. The `CategoryController` is untouched in this slice.
