# Task 013: Consolidate `catalog-market-category-trees` into `catalog-market`

**Status**: completed
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012
**Retry count**: 0

## Description
The `catalog-market-category-trees` package was created as a separate entity, but it does not stand meaningfully on its own — all its functionality is specifically about the `catalog-market` bridge. Merge everything from `catalog-market-category-trees` into `catalog-market`, rename namespaces accordingly, remove `catalog-market-category-trees` entirely, and update all cross-references (composer.json, module.php, tests, docs, FEATURES.md).

After this task, `catalog-market` is the single package a merchant installs to get full Tier 3 behaviour: scoped field registry bridge + category-tree market assignment + resolver + delete plugin.

## Context

### What moves from `catalog-market-category-trees` into `catalog-market`

**Source files** (all namespace `Markommerce\CatalogMarketCategoryTrees\…` → `Markommerce\CatalogMarket\…`):
- `src/Contracts/CategoryTreeMarketAssignmentRepositoryInterface.php`
- `src/Entity/CategoryTreeMarketAssignment.php`
- `src/Exceptions/TreeHasMarketAssignmentsException.php`
- `src/Plugins/CategoryTreeServiceDeletePlugin.php`
- `src/Repositories/CategoryTreeMarketAssignmentRepository.php`
- `src/Services/CategoryTreeMarketAssignmentService.php`
- `src/Services/CategoryTreeMarketResolver.php`

**Test files** (namespace `Markommerce\CatalogMarketCategoryTrees\Tests\…` → `Markommerce\CatalogMarket\Tests\…`):
- `tests/Feature/Helpers/PostgresTestConnection.php`
- `tests/Feature/Repositories/CategoryTreeMarketAssignmentRepositoryIntegrationTest.php`
- `tests/Feature/Tier3EndToEndTest.php`
- `tests/Support/FakeCategoryTreeMarketAssignmentRepository.php`
- `tests/Unit/Contracts/CategoryTreeMarketAssignmentRepositoryInterfaceTest.php`
- `tests/Unit/Entity/CategoryTreeMarketAssignmentTest.php`
- `tests/Unit/Exceptions/TreeHasMarketAssignmentsExceptionTest.php`
- `tests/Unit/FileDeletionTest.php` (if it checks for deleted-from-catalog files — re-verify after move; may need updating or deletion)
- `tests/Unit/ModuleBindingsTest.php`
- `tests/Unit/Plugins/CategoryTreeServiceDeletePluginTest.php`
- `tests/Unit/ReadmeTest.php`
- `tests/Unit/Repositories/CategoryTreeMarketAssignmentRepositoryTest.php`
- `tests/Unit/Repositories/FakeCategoryTreeMarketAssignmentRepositoryTest.php`
- `tests/Unit/Services/CategoryTreeMarketAssignmentServiceTest.php`
- `tests/Unit/Services/CategoryTreeMarketResolverTest.php`

### Changes to `catalog-market`

**`composer.json`** — add the deps that `catalog-market-category-trees` carried:
- `marko/core`
- `marko/database`
- `markommerce/catalog` (already transitively available via `catalog-scope`, but make explicit since we now own the repository)
- Keep existing: `markommerce/catalog-scope`, `markommerce/market`

**`module.php`** — grow from the current no-op shape to:
```php
return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/market'        => '*',
        'markommerce/catalog'       => '*',
    ],
    'bindings' => [
        CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class,
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        // Placeholder: registers no scoped fields until Product gains price/visibility.
        // See FEATURES.md tier 3 for the planned end state.
    },
];
```

**`PackageScaffoldingTest.php`** — update assertions to reflect the expanded composer deps.

**`ReadmeTest.php`** — the existing test carried over from `catalog-market-category-trees` already covers the required sections; verify it passes with the merged content.

**`README.md`** — merge the `catalog-market-category-trees` README into `catalog-market/README.md`. The merged README should cover:
- The placeholder ScopedFieldRegistry bridge status (existing `catalog-market` section).
- Installation and quick examples for `CategoryTreeMarketAssignmentService` and `CategoryTreeMarketResolver`.
- A note that installing the package activates `CategoryTreeServiceDeletePlugin` automatically.

### Removal of `catalog-market-category-trees`

- Delete the entire `packages/catalog-market-category-trees/` directory.
- Remove `markommerce/catalog-market-category-trees` from root `composer.json` `require`.
- Remove `Markommerce\CatalogMarketCategoryTrees\Tests\` from root `composer.json` `autoload-dev.psr-4`.

### Cross-reference updates

- `packages/catalog/tests/Unit/ComposerManifestTest.php` — the absence assertion for `markommerce/catalog-market-category-trees` stays valid (catalog must not depend on it). The absence assertion for `markommerce/catalog-market` also stays.
- `packages/catalog/tests/Unit/MarketDecouplingTest.php` — forbidden substring `Markommerce\\CatalogMarketCategoryTrees` must remain in the list (catalog must never reference the old namespace). After renaming all classes to `Markommerce\CatalogMarket\`, the matching `Markommerce\\CatalogMarket\\` forbidden substring in that same test means catalog must not reference the new namespace either — both strings are still correct.
- `docs/src/content/docs/packages/catalog-market-category-trees.md` — delete.
- `docs/src/content/docs/packages/catalog-market.md` — update to reflect the full merged package scope (no longer a placeholder-only page).
- `docs/src/content/docs/packages/catalog.md` — update the cross-link from `catalog-market-category-trees` to `catalog-market`.
- `tests/Unit/Docs/CatalogMarketExtractPagesTest.php` — update assertions:
  - Remove the assertion that `catalog-market-category-trees.md` exists.
  - Update assertions to check `catalog-market.md` for `CategoryTreeMarketResolver`, `CategoryTreeMarketAssignmentService`, `CategoryTreeServiceDeletePlugin` (these now live in the `catalog-market` docs page).
- `FEATURES.md` — update Tier 3 package count from 18 → 17 (one package removed); update the package list in the Tier 3 row to drop `catalog-market-category-trees`.

### The `Tier3EndToEndTest` bridge manifest path

After the move, the bridge manifest's `path` must point to `packages/catalog-market/` (not `catalog-market-category-trees/`). The `dirname(__DIR__, 2)` expression in the test resolves from `tests/Feature/` — verify it still resolves correctly to the package root.

## Requirements (Test Descriptions)
- [ ] `it moves all src classes to Markommerce\\CatalogMarket\\ namespace and they are autoloadable`
- [ ] `it registers CategoryTreeMarketAssignmentRepositoryInterface binding in catalog-market module.php`
- [ ] `it still boots an empty ScopedFieldRegistry when the boot closure runs (no-op field registration preserved)`
- [ ] `it requires marko/core, marko/database, markommerce/catalog, markommerce/catalog-scope, and markommerce/market in catalog-market composer.json`
- [ ] `it removes the packages/catalog-market-category-trees directory entirely`
- [ ] `it removes markommerce/catalog-market-category-trees from the root composer.json require block`
- [ ] `all relocated unit tests pass under the Markommerce\\CatalogMarket\\Tests\\ namespace`
- [ ] `the Tier3EndToEndTest passes with the bridge manifest path pointing to packages/catalog-market/`
- [ ] `docs/src/content/docs/packages/catalog-market-category-trees.md no longer exists`
- [ ] `docs/src/content/docs/packages/catalog-market.md covers resolver, assignment service, and delete plugin`
- [ ] `CatalogMarketExtractPagesTest passes after its assertions are updated for the merged package structure`
- [ ] `FEATURES.md Tier 3 package count reflects 17 packages (catalog-market-category-trees removed)`
- [ ] `composer test passes from monorepo root with no failures`

## Acceptance Criteria
- `packages/catalog-market-category-trees/` does not exist.
- `packages/catalog-market/src/` contains all the moved source classes under the `Markommerce\CatalogMarket\` namespace.
- `packages/catalog-market/tests/` contains all the moved test files under the `Markommerce\CatalogMarket\Tests\` namespace.
- Root `composer test` passes.
- PHPStan level 8 + PHP-CS-Fixer clean.

## Implementation Notes
- Move files first, then update all namespace strings in one pass before running tests.
- The `FileDeletionTest` inside `catalog-market-category-trees` was asserting that old catalog files no longer exist. After the move, re-evaluate whether this test is still useful in `catalog-market/tests/Unit/` — it could be deleted if it no longer adds signal, or kept as a renamed `CatalogSourceCleanupTest`.
- `composer dump-autoload` must run after moving files and before running tests.
