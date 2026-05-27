# Task 005: Add `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService`

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Add the two new services to `catalog-market-category-trees/src/Services/`. The resolver owns the read path (`resolveTreeForMarket`); the assignment service owns the write path (`assignTreeToMarket`, `unassignMarket`). Both depend on the relocated `CategoryTreeMarketAssignmentRepositoryInterface` and catalog's `CategoryTreeRepositoryInterface`. Relocate the matching test cases from `packages/catalog/tests/Unit/Services/CategoryTreeServiceMarketResolutionTest.php` into two new test files under the new package.

## Context
- The behaviour is a verbatim port of `CategoryTreeService::assignTreeToMarket`, `::unassignMarket`, and `::resolveTreeForMarket`. Method bodies and exception flows transfer unchanged; only the host class and namespace change.
- `CategoryTreeMarketResolver` returns `Markommerce\Catalog\Entity\CategoryTree`. The resolver's fallback path calls `CategoryTreeRepositoryInterface::findDefault()` which throws `DefaultTreeMissingException` from `Markommerce\Catalog\Exceptions` — that import stays a cross-package reference (bridge depends on domain).
- `CategoryTreeMarketAssignmentService::assignTreeToMarket` validates the tree exists and throws `Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException` (also a catalog import — same direction).
- Test relocations:
  - `packages/catalog/tests/Unit/Services/CategoryTreeServiceMarketResolutionTest.php` splits into:
    - `packages/catalog-market-category-trees/tests/Unit/Services/CategoryTreeMarketResolverTest.php` (resolve cases + DefaultTreeMissingException case)
    - `packages/catalog-market-category-trees/tests/Unit/Services/CategoryTreeMarketAssignmentServiceTest.php` (assign + unassign + replace + unknown-tree cases)
  - Both new test files use the relocated `FakeCategoryTreeMarketAssignmentRepository` (now under `Markommerce\CatalogMarketCategoryTrees\Tests\Support\…`) and import catalog's `FakeCategoryTreeRepository` via the root autoload (`Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository`).
  - The original `CategoryTreeServiceMarketResolutionTest.php` is deleted as part of task 007 (catalog-side strip).
- **The relocated `FakeCategoryTreeMarketAssignmentRepository` already implements upsert-by-market semantics** (the `byMarket` map is keyed by market string, so re-saving for the same market overwrites). The `replaces an existing assignment` test relies on this. Do NOT rewrite the fake — just reuse what task 004 moved.
- The `CategoryTreeMarketResolver throws DefaultTreeMissingException when neither a market assignment nor a default tree exist` case is a **NEW** test (not a relocation — the catalog-side test never covered this branch). Verify that `FakeCategoryTreeRepository::findDefault()` throws `DefaultTreeMissingException` when no default tree is registered; if it doesn't, extend the fake (in catalog's `tests/Support/`) under a separate small edit. Most likely it already does, since catalog's own `CategoryTreeServiceTreeCrudTest` exercises this branch.

## Requirements (Test Descriptions)
- [x] `CategoryTreeMarketResolver returns the tree assigned to the given market`
- [x] `CategoryTreeMarketResolver returns the default tree when no assignment exists for the market`
- [x] `CategoryTreeMarketResolver throws CategoryTreeNotFoundException when the assigned tree id no longer exists`
- [x] `CategoryTreeMarketResolver throws DefaultTreeMissingException when neither a market assignment nor a default tree exist`
- [x] `CategoryTreeMarketAssignmentService::assignTreeToMarket stores the assignment for an unknown market`
- [x] `CategoryTreeMarketAssignmentService::assignTreeToMarket replaces an existing assignment for the same market`
- [x] `CategoryTreeMarketAssignmentService::assignTreeToMarket throws CategoryTreeNotFoundException when the tree id is unknown`
- [x] `CategoryTreeMarketAssignmentService::unassignMarket removes the assignment for the given market`
- [x] `CategoryTreeMarketAssignmentService::unassignMarket is a no-op when no assignment exists for the market`

## Acceptance Criteria
- `packages/catalog-market-category-trees/src/Services/CategoryTreeMarketResolver.php` and `CategoryTreeMarketAssignmentService.php` exist with the constructor signatures specified in `_plan.md`'s Architecture Notes.
- Both new test files pass.
- The two services do NOT depend on `CategoryTreeService` (no circular wiring — only catalog's *repository* interfaces are imported).
- **This task does NOT edit `packages/catalog-market-category-trees/module.php`.** Task 004 owns module.php bindings; this task only adds source files. The services do not need explicit bindings (no interface to bind to), and they do not need singleton registration (no mutable state).
- PHPStan + PHP-CS-Fixer clean.
- `@throws` PHPDoc tags accompany every method that can throw.

## Implementation Notes
- Created `CategoryTreeMarketResolver` and `CategoryTreeMarketAssignmentService` in `packages/catalog-market-category-trees/src/Services/`.
- Both services are verbatim ports of the matching methods from `CategoryTreeService` — no dependency on `CategoryTreeService`.
- Test files created at `packages/catalog-market-category-trees/tests/Unit/Services/CategoryTreeMarketResolverTest.php` and `…AssignmentServiceTest.php`.
- Tests use `FakeCategoryTreeRepository` from `Markommerce\Catalog\Tests\Support` and `FakeCategoryTreeMarketAssignmentRepository` from `Markommerce\CatalogMarketCategoryTrees\Tests\Support`.
- PHPStan level 8 and PHP-CS-Fixer both clean.
- All 44 package tests pass (34 pre-existing + 9 new).
