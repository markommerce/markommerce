# Task 019: Update `CatalogSeeder` to populate the default tree

**Status**: completed
**Depends on**: 017, 018
**Retry count**: 0

## Description
Modify `CatalogSeeder` so that after seeding categories, it ensures a default tree exists and places every seeded category as a root node in the default tree. The new **tree-placement step** must be idempotent: if a category already has a placement in the default tree, do not create a second one. (Product and assignment seeding remain destructive-on-rerun as today — the historical contract is "seeder runs against a clean DB".)

## Context
- Modify existing file: `packages/catalog/Seed/CatalogSeeder.php`
- Inject `CategoryTreeService` into the seeder constructor (alongside existing dependencies)
- After categories are seeded, call `categoryTreeService->ensureDefaultTreeExists()` to obtain the default tree
- For each seeded category, check whether it already has a placement in the default tree (`categoryTreeNodeRepository->findByCategoryInTree($categoryId, $treeId)`); if not, call `categoryTreeService->placeCategory($defaultTree->id, $category->id, parentNodeId: null, position: null)` and let the service auto-assign monotonically increasing positions (see task 015 — `placeCategory` auto-assigns position when null).
- **Update existing test file**: `packages/catalog/tests/Unit/Seed/CatalogSeederTest.php` — six existing test cases construct `new CatalogSeeder(productRepository: …, categoryRepository: …, assignmentRepository: …)`. Each must be updated to pass the new `CategoryTreeService` dependency. Build a shared helper, e.g. `makeCatalogSeeder(...)`, that constructs the seeder with all dependencies wired (using the fakes from tasks 007, 008, 009). The helper signature should accept optional repository overrides so individual tests can swap one fake while keeping the rest defaults.
- New feature test: `packages/catalog/tests/Feature/CatalogSeederTreeTest.php` (runs the full seeder end-to-end against the real DB; uses the same Postgres test infrastructure as `config-pgsql`).

## Requirements (Test Descriptions)
- [ ] `existing seeder unit tests continue to pass with the new CategoryTreeService dependency`
- [ ] `running the seeder creates the default tree when none exists`
- [ ] `running the seeder reuses an existing default tree without creating a duplicate`
- [ ] `running the seeder places every seeded category as a root node in the default tree`
- [ ] `the tree-placement step is idempotent: when seeded categories already have a placement in the default tree, the seeder does not create a second placement for the same (category, tree) pair`
- [ ] `seeded categories appear in stable position order in the default tree`

## Acceptance Criteria
- `CategoryTreeService` injected into `CatalogSeeder`
- Existing seed behaviour (product creation, locale overrides, assignments) preserved — all six existing `CatalogSeederTest` cases pass after update
- Feature test passes against the real DB driver
- PHPStan level 8 clean

## Implementation Notes
- The seeder does not need to inject `CategoryTreeNodeRepositoryInterface` directly — the idempotency check can be expressed via the service if `CategoryTreeService` exposes a helper (e.g. `placeCategoryIfAbsent`) OR the seeder can call the node repository directly. Recommended: keep the seeder thin by adding a private helper inside `CatalogSeeder::placeCategoriesInDefaultTree(array $categories): void` that loops over each category, calls `placeCategory` only after a `findByCategoryInTree` check. Inject `CategoryTreeNodeRepositoryInterface` for the check.
- Final seeder constructor signature (suggested):
  ```php
  public function __construct(
      private readonly ProductRepositoryInterface $productRepository,
      private readonly CategoryRepositoryInterface $categoryRepository,
      private readonly ProductCategoryAssignmentRepositoryInterface $assignmentRepository,
      private readonly CategoryTreeService $categoryTreeService,
      private readonly CategoryTreeNodeRepositoryInterface $categoryTreeNodeRepository,
  ) {}
  ```
