# Task 004: Simplify ProductGridComponent to read raw values

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Strip the `ScopeResolver` injection from `ProductGridComponent`. After this task, the component populates `ProductGridData` using raw `$product->name` and `$product->description` values directly. The `resolvedNames`/`resolvedDescs` map fields on `ProductGridData` stay (catalog-scope's `ScopedProductGridComponent` will populate them with resolved values via `#[Preference]` in task 008) — they are populated with identity values (the raw name/description) when the plain component runs alone.

This keeps the data contract identical for the Latte view layer (no view changes needed in either tier), and gives `ScopedProductGridComponent` a clean `parent::data()` call to extend.

## Context
- Related files:
  - `packages/catalog/src/Component/ProductGridComponent.php` — drop `Markommerce\Scope\Resolver\ScopeResolver` import and constructor param; populate `resolvedNames`/`resolvedDescs` identity-mapped from `$product->name`/`$product->description`
  - `packages/catalog/tests/Unit/Component/ProductGridComponentTest.php` — drop the scope-aware test cases (`resolves product names through ScopeResolver rather than the raw column value` and any case that constructs a `productGridBuildScopeResolver()` helper); the scope-aware cases relocate to catalog-scope's test suite in task 008
- Patterns to follow: existing component tests in catalog use Pest with reflection-based assertions on constructor signatures

## Requirements (Test Descriptions)
- [ ] `it takes only CategoryRepositoryInterface and CategoryAssignmentService in its constructor (no ScopeResolver)`
- [ ] `it has no Markommerce\\Scope imports in the ProductGridComponent class file`
- [ ] `it returns a ProductGridData with the raw product name in resolvedNames keyed by product id`
- [ ] `it returns a ProductGridData with the raw product description in resolvedDescs keyed by product id`
- [ ] `it returns an empty products list when the category has no id`
- [ ] `it skips products with null id when building the resolved maps`

## Acceptance Criteria
- All requirements have passing tests.
- `ProductGridComponent`'s constructor signature has exactly two parameters (the two repository/service dependencies).
- `ProductGridData` shape is unchanged — only the population logic differs.
- No `Markommerce\Scope` references remain in `packages/catalog/src/Component/`.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
