# Devil's Advocate Review: catalog-tree-v1

## Critical (Must fix before building)

### C1. Repository interface signatures incompatible with `Marko\Database\Repository\RepositoryInterface`
**Tasks affected**: 007, 008, 009, 010, 011, 012

Tasks 007/008/009 declare interface method signatures (e.g. `find(int $id): ?CategoryTree`, `findAll(): list<CategoryTree>`, `save(CategoryTree $tree): void`) that **cannot coexist** with the framework base. The concrete classes in tasks 010-012 extend `Marko\Database\Repository\Repository`, which implements `RepositoryInterface` with these mandatory signatures:

- `find(int|string $id): ?Entity`
- `findOrFail(int|string $id): Entity`
- `findAll(): EntityCollection`
- `findBy(array $criteria): EntityCollection`
- `findOneBy(array $criteria): ?Entity`
- `existsBy(array $criteria): bool`
- `save(Entity $entity): void`
- `delete(Entity $entity): void`
- `insertBatch(array $entities): void`

PHP will reject any subclass narrowing these signatures (LSP). The existing `CategoryRepositoryInterface` resolves this by simply `extends RepositoryInterface<Category>` without re-declaring `find`, `findAll`, etc. — the docblock `@extends RepositoryInterface<Category>` types the generic.

**Fix**: Restructure tasks 007/008/009 so each new repository interface follows the existing pattern: `extends RepositoryInterface<EntityClass>` and only adds the *custom* methods. The fakes must mirror `FakeCategoryRepository` (implementing the full base contract including `findOrFail`, `findBy`, `findOneBy`, `existsBy`, `insertBatch`) and only add the new custom methods.

For `CategoryTreeRepositoryInterface`:
- Drop `find`, `findAll`, `save`, `delete` from the interface declaration
- Keep custom methods: `findByCode(string $code): ?CategoryTree`, `findDefault(): CategoryTree` (throws `DefaultTreeMissingException`)
- Service code should call `find()` from the base contract; concrete repo uses `findOneBy(['code' => …])` for `findByCode`

For `CategoryTreeNodeRepositoryInterface`:
- Drop base methods; keep custom: `findByTree`, `findChildren`, `findRoots`, `findByCategoryInTree`, `findByCategoryAcrossTrees`

For `CategoryTreeMarketAssignmentRepositoryInterface`:
- Drop base; keep custom: `findByMarket(string $market): ?CategoryTreeMarketAssignment`, `findByTree(int $treeId): list<CategoryTreeMarketAssignment>`
- `findAll()` already exists on the base (returns `EntityCollection`) — drop from custom

### C2. `CategoryTreeMarketAssignment` string PK is incompatible with the default Repository::save path
**Tasks affected**: 006, 012

`Repository::save()` calls `hydrator->isNew($entity, $this->metadata)` to decide insert vs update. The default `EntityHydrator` heuristic for "new" is "primary key is null" — but a string PK has no autoincrement, so an entity is constructed with `market='us'` and is *immediately not null*. Without an "originalValues" snapshot loaded from DB, the hydrator could go either way depending on its implementation.

Even if `save` correctly INSERTs the first time, an upsert (replace existing assignment for the same market) is not natively supported.

**Fix**: Task 012 must:
1. Explicitly verify behaviour against `EntityHydrator::isNew()` for string-PK entities (`Marko\Database\Entity\EntityHydrator`).
2. If `save()` cannot upsert by string PK natively, override `save()` to:
   - `findByMarket($entity->market)` first; if exists, do UPDATE; else INSERT.
   - Wrap in a transaction.
   - Or use Postgres-specific `INSERT … ON CONFLICT (market) DO UPDATE` (since the only driver in scope is Postgres).

Add a test requirement: `save() correctly inserts a new market and updates when the same market PK already exists`.

### C3. Task 019 will break existing CatalogSeederTest
**Tasks affected**: 019

`packages/catalog/tests/Unit/Seed/CatalogSeederTest.php` constructs `CatalogSeeder` with 3 dependencies via `new CatalogSeeder(productRepository: …, categoryRepository: …, assignmentRepository: …)` in 6+ places. Adding `CategoryTreeService` to the constructor (or the underlying repo) will break every existing test in that file.

**Fix**: Task 019 must explicitly list "update all existing `CatalogSeederTest` cases to pass the new `CategoryTreeService` dependency" as a requirement. It should also explicitly note the existing seeder behavioural tests (e.g. "seeds the configured number of categories") must continue to pass — the new functionality is additive.

Additionally, building a `CategoryTreeService` in a unit test requires constructing the service with 4 repository dependencies (tree, market-assignment, node, category). The test file should add a `makeCategoryTreeService(): CategoryTreeService` helper that wires all four fakes.

## Important (Should fix before building)

### I1. Service-instance state persists across constructor expansions (013→017)
**Tasks affected**: 013, 014, 015, 016, 017

The `CategoryTreeService` constructor grows across tasks: 013 starts with 2 deps, 015 adds 2 more (`CategoryTreeNodeRepositoryInterface`, `CategoryRepositoryInterface`). Each later task must NOT break the test files of earlier tasks. The plan is implicit about this — make it explicit.

**Fix**: Tasks 014/015/016/017 must each include a requirement bullet: "All previously-added tests under `packages/catalog/tests/Unit/Services/CategoryTreeService*Test.php` continue to pass with the expanded constructor." The simplest pattern is a shared `tests/Support/makeCategoryTreeService.php` helper that returns a fully wired service.

### I2. `removeNode` and other mutators need `tree_id` for cache invalidation
**Tasks affected**: 015, 016, 017

`removeNode(int $nodeId, NodeRemovalStrategy $strategy): void` does not receive `tree_id`. Cache invalidation by `tree_id` requires looking up the node first (`find($nodeId)`) to read `node->treeId`. Same applies to `moveNode`. This is feasible but must be explicit.

**Fix**: Add an implementation note to task 017: "Mutators (`moveNode`, `removeNode`) must first load the affected node to determine its `tree_id` before invalidating the cache. Cache invalidation occurs after the mutation completes."

Also clarify: `placeCategory` already receives `tree_id` explicitly — invalidate `materializedTreeCache[$treeId]` directly.

`reorderSiblings(?int $parentNodeId, int $treeId, …)` receives `treeId` directly — invalidate via that.

### I3. `placeCategory` position collision and `position` semantics
**Tasks affected**: 015

`placeCategory(int $treeId, int $categoryId, ?int $parentNodeId, int $position = 0): CategoryTreeNode` defaults `position` to 0. The unique index `(tree_id, parent_node_id, position)` will fail for the *second* placement under the same parent if both use 0.

**Fix**:
- Either: drop the `int $position = 0` default and **compute** the next position automatically as `MAX(position) + 10` among siblings of `$parentNodeId` in `$treeId` when not explicitly passed.
- Or: make `$position` required and add a requirement: "two consecutive calls to placeCategory under the same parent assign monotonically increasing positions".

Choose option A (auto-compute). Update task 015 to:
- Change signature to `placeCategory(int $treeId, int $categoryId, ?int $parentNodeId = null, ?int $position = null): CategoryTreeNode`
- Add requirement: "placeCategory assigns the next available position (max + 10) when position is null"
- Add requirement: "placeCategory respects an explicitly provided position"

This also affects task 019 (seeder): it can simply pass `position: null` for each category and rely on the service to auto-assign.

### I4. `placeCategory` parent-belongs-to-same-tree validation needs to be explicit
**Tasks affected**: 015

The description says "verifies tree exists, category exists, parent node (if any) belongs to the same tree". The test bullet "`placeCategory throws NodeNotInTreeException when parent node belongs to a different tree`" exists, but the parent-not-found case is missing.

**Fix**: Add to task 015 test requirements:
- `placeCategory throws CategoryTreeNodeNotFoundException when parentNodeId is provided but the parent node does not exist`

### I5. `reorderSiblings` parent uniformity check missing from test requirements
**Tasks affected**: 016

The description says the method verifies every node belongs to the **same tree AND same parent**, but only the tree-mismatch test is listed.

**Fix**: Add to task 016 test requirements:
- `reorderSiblings throws NodeNotInTreeException when a listed node has a different parent than expected` (the same exception is reused, or introduce a separate `NodeNotInSiblingGroupException`)
- Actually, re-using `NodeNotInTreeException` for "wrong parent" is semantically wrong. Add a dedicated `NodeNotASiblingException` or extend the existing exception's factories. Simpler: add a new factory `NodeNotInTreeException::forNodeAndParent(int $nodeId, ?int $expectedParentNodeId)` OR add a new exception in task 003. Pick the simpler path: add `forNodeAndParent` factory to `NodeNotInTreeException`.

Update task 003: `NodeNotInTreeException` gets an additional factory `forParentMismatch(int $nodeId, ?int $expectedParentId, ?int $actualParentId)`.

Update task 016 requirements:
- `reorderSiblings throws NodeNotInTreeException (forParentMismatch) when a listed node belongs to a different parent than the rest`

### I6. Cycle-detection edge cases missing
**Tasks affected**: 015

Cycle detection needs to handle:
1. Self-parent: `moveNode(nodeId=5, newParentNodeId=5)` — moving a node under itself.
2. Direct cycle: parent under child (depth 1).
3. Deeper cycle (already covered).
4. Moving to null parent (always safe — no test needed but the algorithm must short-circuit).

**Fix**: Add to task 015 test requirements:
- `moveNode throws CircularNodeReferenceException when newParentNodeId equals nodeId (self-parent)`
- `moveNode allows moving a node to the root (newParentNodeId is null) without cycle detection complaint`

### I7. `setDefaultTree` and `assignTreeToMarket` missing not-found semantics
**Tasks affected**: 013, 014

`setDefaultTree(int $treeId)` description doesn't enumerate `CategoryTreeNotFoundException` though it logically should throw it. Same for `assignTreeToMarket` (covered in task 014, good).

**Fix**:
- Task 013: add to `setDefaultTree`'s `throws` list: `CategoryTreeNotFoundException` when the tree id is unknown.
- Task 013: add test requirement: `setDefaultTree throws CategoryTreeNotFoundException when tree id is unknown`.

### I8. Default-tree invariant race window
**Tasks affected**: 013

Service-level enforcement of "exactly one default tree" requires:
1. `createTree(isDefault=true)`: check `findDefault()` does not exist → insert with `is_default=true`. If two parallel callers both pass the check and both insert, you get two defaults.
2. `setDefaultTree($id)`: find current default → set its `is_default=false` → save → set new default. A failure between the two saves leaves zero defaults.

Both windows are tolerable for single-admin workflows (per Architecture Notes risk section). But the test plan should at least verify the *single-threaded* invariant:

**Fix**: Add to task 013 test requirements:
- `setDefaultTree leaves exactly one default tree (no overlap, no gap) after the swap`
- The implementation note already mentions documenting this; keep it.

### I9. Existing `ModuleBindingsTest` already exists — task 020 says "create if missing"
**Tasks affected**: 020

`packages/catalog/tests/Unit/ModuleBindingsTest.php` already exists. Task 020 should be unambiguous: **extend** the existing file, do not create.

**Fix**: Replace "create if missing — there may be an existing test asserting the structure" with "extend the existing `ModuleBindingsTest.php` file by adding three new `it(...)` cases for the new bindings. The existing four cases must continue to pass."

### I10. Task 019 idempotency vs existing destructive seed behaviour
**Tasks affected**: 019

`CatalogSeeder` currently calls `$this->productRepository->insertBatch($entities)` with 5000 products generated by `random_int`. Running the seeder twice will:
- Attempt to insert 5000 more products → SKU unique constraint violation (the SKU pattern is deterministic: `SKU-000001`…`SKU-005000`).

The plan claims "running the seeder twice does not create duplicate placements for the same category" — but this is moot if the seeder already explodes on product re-insert.

**Fix**: Make explicit in task 019 that the *placement portion* must be idempotent, but the seeder as a whole has always been intended to run against a clean DB. The new idempotency requirement applies only to the new tree-placement step (so running an integration test that re-seeds doesn't accidentally double-place if the products somehow survived).

Update task 019 requirements:
- Replace "running the seeder twice does not create duplicate placements for the same category" with "the tree-placement step is idempotent: when seeded categories already have a placement in the default tree, the seeder does not create a second placement for the same (category, tree) pair"
- Clarify in the description that this protection guards against partial seed reruns, not full second runs.

### I11. Default-tree fallback for `resolveTreeForMarket` interacts with `assignTreeToMarket`
**Tasks affected**: 014

`assignTreeToMarket(int $treeId, string $market)` — if the tree was deleted between assignment and resolution (and the FK has `onDelete: RESTRICT`), the deletion would have failed. But if the assignment row was created against a non-existent tree (would FK-violate at DB level) the fake doesn't catch this.

**Fix**: Task 014 explicitly says it throws `CategoryTreeNotFoundException` when tree id is unknown. Test bullet already exists. No additional change needed, BUT the fake must verify the tree exists at the time of assignment (since the fake doesn't enforce FKs). Confirm task 014 test uses both fakes: tree fake (to seed a real tree id) and market-assignment fake.

Add an implementation note to task 014: "Validate the tree exists by calling `$this->categoryTreeRepository->find($treeId)` before saving the assignment."

### I12. Task 018 missing `CategoryNotFoundException` import context
**Tasks affected**: 018

`CategoryService::delete()` throws `CategoryNotFoundException` (existing exception). Task 018 lists it in the throws description but its dependency list is `[003, 008]` — `003` covers tree-node exceptions but not the existing `CategoryNotFoundException` (which already exists in code, no task needed). Confirm the dependency on 008 is for `CategoryTreeNodeRepositoryInterface` and the existing `CategoryRepositoryInterface` is implicit (already in code).

**Fix**: Add an "existing dependencies" note to task 018: "Reuses existing `CategoryRepositoryInterface` and `CategoryNotFoundException` from the catalog module — no new task creates these."

### I13. Task 021 feature test resolution path
**Tasks affected**: 021

The feature test claims to "resolve `CategoryTreeService` through the Marko container". The container needs to be able to:
- Resolve `CategoryTreeService` (concrete class) — likely auto-resolved if Marko supports constructor-based DI on concrete classes.
- Resolve all four repository interfaces from module.php — covered by task 020.

But `CategoryTreeService` constructor takes 4 repository interfaces. If the container doesn't auto-wire concrete classes, the service won't be resolvable.

**Fix**: Investigate during task 021 whether `CategoryTreeService` needs explicit binding (`CategoryTreeService::class => CategoryTreeService::class`) in `module.php`. Most likely Marko's container auto-resolves concrete classes — confirm against existing services (e.g. `CategoryAssignmentService`, `ProductService`) which appear to work without explicit bindings.

Add to task 020 a verification step: "If concrete services are not auto-resolvable by the Marko container, also bind `CategoryTreeService::class => CategoryTreeService::class` and `CategoryService::class => CategoryService::class`."

### I14. Position uniqueness vs nullable parent_node_id
**Tasks affected**: 005

The unique index `(tree_id, parent_node_id, position)` includes a nullable column. In PostgreSQL, `NULL` values in a unique index are treated as **distinct** (multiple roots at position=0 are allowed by the index). MySQL has different semantics.

This is actually fine because Markommerce ships only against Postgres in this slice — but the service must compute root positions correctly (handled by I3).

**Fix**: Add a note to task 005 implementation notes: "On Postgres, NULL `parent_node_id` values are treated as distinct in the unique index — the service layer is responsible for assigning unique `(tree_id, null, position)` tuples for root nodes via the auto-position logic in `placeCategory`."

### I15. `findByCategoryAcrossTrees` as the deletion guard primitive
**Tasks affected**: 008, 018

Using `findByCategoryAcrossTrees` and `count(result) > 0` works but pulls all rows. For an `exists` check, a dedicated `existsByCategoryAcrossTrees(int $categoryId): bool` is cheaper. However:
- The plan also wants `CategoryHasPlacementsException` to carry the placement *count* (task 003, task 018 test requirement: "carries the placement count for diagnostics"), which requires fetching all rows OR a dedicated `countByCategory` method.

**Fix**: Acceptable trade-off as-is. Add an implementation note to task 008: "`findByCategoryAcrossTrees` is intentionally row-returning (not boolean) because callers need the count for diagnostics. If profiling shows hot-path concerns in v2, add a separate `countByCategory(int $categoryId): int` method."

## Minor (Nice to address)

### M1. Position gap convention
Task 016 mentions a recommended gap of 10. Codify this as a class constant in `CategoryTreeService` (`private const int POSITION_GAP = 10;`) so both `reorderSiblings` and `placeCategory` (per I3) share it.

### M2. Repository contract test for new interfaces
The existing `RepositoryContractsTest.php` asserts that `ProductRepositoryInterface` extends `RepositoryInterface`. Tasks 007/008/009 could add analogous contract tests to assert the new interfaces extend the base. Currently only the fake tests are required.

### M3. Materialised tree shape: array key choices
Task 017 specifies the array shape uses string keys `'node'`, `'category_id'`, `'children'`. Consider whether a typed value object (`MaterializedTreeNode` with public readonly props) would be more PHP-idiomatic and easier to type-check under PHPStan level 8. Arrays-with-string-keys are notoriously hard to type with `array<string, mixed>`.

### M4. Reuse `EntityCollection<CategoryTree>` over `list<CategoryTree>`
Task 007 declares `findAll(): list<CategoryTree>`. Per C1, this method should not be re-declared at all (inherited from the base). The base returns `EntityCollection` — consistent with the rest of the codebase.

### M5. Path string convention for tests
Task 010 paths say `tests/Feature/Repositories/...IntegrationTest.php`. Existing pattern is `tests/Feature/RepositoryImplementationsTest.php` (no `Repositories/` subdirectory). Decide and keep consistent.

## Questions for the Team

### Q1. Should `Category::delete()` also remove `ProductCategoryAssignment` rows?
The plan deletes the category via `categoryRepository->delete()`. The FK on `ProductCategoryAssignment.category_id` is `onDelete: CASCADE`. So the DB does the cleanup. Confirm this is the intended behaviour — orphaned products lose their category assignments silently. Is that visible-enough for "no silent failures"?

### Q2. Tree code on creation: human-friendly vs auto-slug
`createTree(string $code, string $name, …)` requires the caller to provide a stable `code`. Should the service slug `name` when `code` is empty? Or error? Current plan: error implicit, no slug. Confirm.

### Q3. Materialised cache hit on mid-request mutation by a different service instance
The cache is per-instance. If two service instances are constructed in one request (rare but possible — e.g. a controller and a console command in the same process), they don't share state. Acceptable for v1?

### Q4. Default tree creation race
`ensureDefaultTreeExists()` is called by the seeder AND "at install time". What is the "install time" hook? Is there an install/bootstrap event in Marko? If the seeder is the only call site, drop the "install time" mention from the plan to avoid implying a hook that doesn't exist.

### Q5. Should `CategoryTreeService` be split into multiple services?
Across tasks 013-017 the service grows to ~12 methods. Magento splits these into `Tree`, `Node`, `Category-in-tree` services. Markommerce currently treats it as one cohesive service. Confirm.

### Q6. `Index` attribute `IS_REPEATABLE` and entity column names with prefixes
Both `CategoryTree.is_default` (snake-case in DB via `Column(name: 'is_default')`) and `CategoryTreeNode.parent_node_id` use explicit column names. PHPCS / CS-Fixer may have opinions about double-naming. Not blocking — confirm the existing codebase doesn't trip on it (existing `Product::createdAt` patterns).

### Q7. Seeder idempotency vs reseeding workflow
The seeder runs against a clean DB historically. Are there now scenarios where it runs against a non-empty DB (idempotent reseed)? If yes, all product/assignment inserts also need idempotency, not just tree placements. If no, drop the "running the seeder twice" test in task 019 — it's misleading.
