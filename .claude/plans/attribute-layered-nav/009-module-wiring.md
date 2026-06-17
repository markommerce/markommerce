# Task 009: Module wiring (catalog-attribute-storefront + catalog filter registry)

**Status**: completed
**Depends on**: 005, 006
**Retry count**: 0

## Description
Wire the new pieces as Marko modules: register the `ProductListFilterRegistry` (catalog) singleton,
register the `AttributeProductListFilter` into it (catalog-attribute-storefront boot), and bind the
facet query + assembler services.

## Context
- `catalog/module.php`: register `ProductListFilterRegistry` as a singleton in the `singletons` array
  (mirror `CategorySortOrderRegistry`, line ~40). It MUST be a singleton so the instance the
  `catalog-attribute-storefront` boot registers into is the SAME instance autowired into
  `CategoryAssignmentService` (task 004 injects it). Do NOT register any attribute filter here (catalog
  stays attribute-agnostic). `CategoryAssignmentService` is autowired (not explicitly bound), so it
  picks up the new constructor dependency automatically once the singleton exists.
- `catalog-attribute-storefront/module.php`:
  - `bindings`: bind the `LayeredNavigation` assembler + the attribute filter + any interfaces produced.
  - `boot`: resolve `ProductListFilterRegistry` + `AttributeProductListFilter` and
    `register($attributeFilter)` so the listing applies it. Mirror the indexer-registry lazy/eager
    lesson from Phase 4 — if registering the filter forces a heavy dependency graph at boot, register a
    lazy wrapper; otherwise eager is fine (the filter's deps are light). Document the choice.
- `catalog-attribute-index/module.php`: bind `AttributeFacetQuery` (+ the shared constraint builder if it
  needs binding).
- Verify no `catalog` → `catalog-attribute*` dependency is introduced.

## Requirements (Test Descriptions)
- [x] `it registers the ProductListFilterRegistry singleton in the catalog module`
- [x] `it registers the AttributeProductListFilter into the registry after boot`
- [x] `it binds the AttributeFacetQuery service`
- [x] `it binds the layered-navigation assembler`

## Acceptance Criteria
- Modules boot without error; the attribute filter is registered so the listing applies it; facet
  query + assembler resolvable. `catalog` has no attribute dependency.

## Implementation Notes

### What was done
- Requirement 1 (`ProductListFilterRegistry` singleton in catalog): already implemented by Task 004.
  Test added to `packages/catalog-attribute-storefront/tests/Unit/ModuleWiringTest.php` to confirm.

- Requirement 2 (`AttributeProductListFilter` registered at boot): added `boot` closure to
  `packages/catalog-attribute-storefront/module.php`. Eager registration chosen because
  `AttributeProductListFilter`'s deps (`AttributeDefinitionRepositoryInterface`,
  `SignatureCandidateEnumerator`, `ScopeContext`, `AttributeExistsClause`) are all lightweight
  request-scoped objects with no heavy I/O at construction time.

- Requirement 3 (`AttributeFacetQuery` binding): already implemented by Task 005.
  Test added to confirm it is present in `catalog-attribute-index/module.php`.

- Requirement 4 (`LayeredNavigationAssembler` binding): the pre-scaffolded
  `src/LayeredNavigation/LayeredNavigationAssembler.php` (along with `LayeredNavigationResult.php`
  and `ActiveFilter.php`) already existed in the package. Added `LayeredNavigationAssembler::class`
  to the `bindings` array in `catalog-attribute-storefront/module.php`.

### Collateral fix
The pre-scaffolded `tests/Unit/LayeredNavigation/LayeredNavigationAssemblerTest.php` had two bugs
that caused the whole test suite (running the directory) to fail with exit code 2:
1. `makeFakeLabelResolver()` tried to extend `readonly class ScopedOptionLabelResolver` — PHP 8.2+
   prohibits extending a readonly class with a non-readonly one. Fixed by constructing a real
   `ScopedOptionLabelResolver` with stub scope deps (`QueryableAttributeDefinitionRepository` +
   no-op `ScopeWalker`).
2. `PaginationPresentation::Grid` enum case doesn't exist; fixed to `PaginationPresentation::Numbered`.
3. The `CategorySortOrderInterface` anonymous stub was missing 3 methods (`key`, `label`, `supportsKeyset`);
   added them.

### Catalog has no attribute dependency
Verified: `catalog/module.php` only adds `ProductListFilterRegistry` to singletons — no
`catalog-attribute*` namespace imports exist.
