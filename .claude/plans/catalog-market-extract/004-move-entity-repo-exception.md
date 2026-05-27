# Task 004: Move entity, repository, interface, exception, and matching tests into the new package

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Atomic source move: relocate everything market-coupled out of `catalog` into `catalog-market-category-trees`. The entity, its repository (with its `INSERT … ON CONFLICT` upsert override), the repository interface, the `TreeHasMarketAssignmentsException`, the in-memory fake repository, and the four corresponding test files all transfer. Namespaces rotate from `Markommerce\Catalog\…` to `Markommerce\CatalogMarketCategoryTrees\…`. Register the repository binding in the new package's `module.php`. Catalog's `module.php` and `CategoryTreeService` are NOT modified yet — task 007 handles the catalog-side strip after the new package is fully wired.

## Context
- Files moving from `packages/catalog/`:
  - `src/Entity/CategoryTreeMarketAssignment.php`
  - `src/Contracts/CategoryTreeMarketAssignmentRepositoryInterface.php`
  - `src/Repositories/CategoryTreeMarketAssignmentRepository.php` (preserves the upsert override docblock and the `ON CONFLICT (market)` SQL verbatim)
  - `src/Exceptions/TreeHasMarketAssignmentsException.php`
  - `tests/Support/FakeCategoryTreeMarketAssignmentRepository.php`
  - `tests/Unit/Entity/CategoryTreeMarketAssignmentTest.php`
  - `tests/Unit/Exceptions/TreeHasMarketAssignmentsExceptionTest.php`
  - `tests/Unit/Repositories/FakeCategoryTreeMarketAssignmentRepositoryTest.php`
  - `tests/Feature/Repositories/CategoryTreeMarketAssignmentRepositoryIntegrationTest.php`
- Destination: `packages/catalog-market-category-trees/{src,tests}/...` with mirrored directory layout under the new namespace.
- Namespace transformations:
  - `Markommerce\Catalog\Entity\CategoryTreeMarketAssignment` → `Markommerce\CatalogMarketCategoryTrees\Entity\CategoryTreeMarketAssignment`
  - `Markommerce\Catalog\Contracts\CategoryTreeMarketAssignmentRepositoryInterface` → `Markommerce\CatalogMarketCategoryTrees\Contracts\…`
  - `Markommerce\Catalog\Repositories\CategoryTreeMarketAssignmentRepository` → `Markommerce\CatalogMarketCategoryTrees\Repositories\…`
  - `Markommerce\Catalog\Exceptions\TreeHasMarketAssignmentsException` → `Markommerce\CatalogMarketCategoryTrees\Exceptions\…`
  - `Markommerce\Catalog\Tests\Support\FakeCategoryTreeMarketAssignmentRepository` → `Markommerce\CatalogMarketCategoryTrees\Tests\Support\…`
- Integration test still references `PostgresTestConnection`. **Copy** `packages/catalog/tests/Feature/Helpers/PostgresTestConnection.php` into `packages/catalog-market-category-trees/tests/Feature/Helpers/PostgresTestConnection.php` (preserving the relative `require_once` in the integration test). The other packages that need a Postgres connection (`scope-pgsql`, `config-pgsql`) each ship their own copy of this helper — this is the project's established pattern. Cross-package `require_once` paths that traverse out of the package directory bake the monorepo layout into the test file and are brittle. The duplication is intentional and acceptable.
- The entity preserves its `#[Table('catalog_category_tree_market_assignments')]` table name — the table name stays prefixed with `catalog_` for consistency with the other catalog tables it FKs against. The new package owns the table, but the prefix encodes the domain it bridges.
- New package's `module.php` registers `CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class` (both under the new namespace).

## Requirements (Test Descriptions)
- [x] `it relocates CategoryTreeMarketAssignment entity to Markommerce\\CatalogMarketCategoryTrees\\Entity namespace with the catalog_category_tree_market_assignments table attribute preserved`
- [x] `it relocates CategoryTreeMarketAssignmentRepositoryInterface to Markommerce\\CatalogMarketCategoryTrees\\Contracts namespace and preserves its findByMarket and findByTree signatures`
- [x] `it relocates CategoryTreeMarketAssignmentRepository to Markommerce\\CatalogMarketCategoryTrees\\Repositories namespace and preserves the INSERT … ON CONFLICT upsert behaviour in save()`
- [x] `it relocates TreeHasMarketAssignmentsException to Markommerce\\CatalogMarketCategoryTrees\\Exceptions namespace with the same forTreeId static factory shape`
- [x] `it relocates FakeCategoryTreeMarketAssignmentRepository to the new package's tests/Support with the new namespace and still satisfies the relocated interface`
- [x] `it relocates CategoryTreeMarketAssignmentTest, TreeHasMarketAssignmentsExceptionTest, FakeCategoryTreeMarketAssignmentRepositoryTest, and CategoryTreeMarketAssignmentRepositoryIntegrationTest under the new namespace and the relocated suites pass`
- [x] `it registers CategoryTreeMarketAssignmentRepositoryInterface => CategoryTreeMarketAssignmentRepository in the new module.php bindings`
- [x] `it deletes the moved files from packages/catalog/src and packages/catalog/tests, leaving no orphaned copies`

## Acceptance Criteria
- Every moved file exists at its new location with the rewritten namespace.
- Every moved test file passes under the new package.
- The original files no longer exist in `packages/catalog/`.
- The catalog test suite is intentionally LEFT broken at the end of this task (`CategoryTreeService.php` still imports the old namespaces and tests still reference them) — task 007 fixes the catalog side. The implementer should NOT attempt partial catalog-side cleanups here.
- The relocated `CategoryTreeMarketAssignmentRepositoryInterface` must continue to `extends Marko\Database\Repository\RepositoryInterface` (the relocated `FakeCategoryTreeMarketAssignmentRepositoryTest` asserts this at line 10-15 of the catalog-side version — preserved verbatim).
- New package's module.php contains the binding for the relocated interface → relocated repository.
- `packages/catalog-market-category-trees/tests/Feature/Helpers/PostgresTestConnection.php` exists as a verbatim copy of catalog's helper.
- PHPStan + PHP-CS-Fixer clean for the new package.
- **Workers must NOT run `composer test` against `packages/catalog/` after this task completes** — catalog is intentionally in a broken intermediate state until task 007 lands. Validate this task's correctness by running ONLY the new package's tests (e.g., `./vendor/bin/pest packages/catalog-market-category-trees`).

## Implementation Notes

All files moved and namespaces updated. The `module.php` now registers the binding. PHPCS auto-fixed the `forTreeId` method signature to use multiline params. PHPStan is clean on `src/` (tests are excluded per project phpstan.neon config). All 27 unit tests and 7 integration tests pass. The catalog package files have been deleted — catalog is intentionally left in a broken state pending task 007.
