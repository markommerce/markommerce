# Task 008: Package READMEs

**Status**: completed
**Depends on**: 003, 005, 007
**Retry count**: 0

## Description
Write `packages/catalog-storefront/README.md` and `packages/catalog-storefront-scope/README.md` from scratch following the Package README Standards in `docs/DOCS-STANDARDS.md`. Trim `packages/catalog/README.md` to remove every storefront mention (storefront route section, controller details, layout snippet) and replace with a brief cross-link pointing readers at catalog-storefront. Trim `packages/catalog-scope/README.md` similarly to remove the `ScopedProductGridComponent` section now that the class lives in catalog-storefront-scope.

Each README follows the same skeleton used by P2 task 012's deliverables: title, intro paragraph, Installation, Quick Example, optional sections (Module Bindings, Storefront Route, etc.), and a Documentation footer link to the markommerce.dev docs page.

## Context
- Reference READMEs from P2:
  - `packages/catalog-scope/README.md` — bridge package with companion entities
  - `packages/catalog-locale/README.md` — minimal bridge with a boot closure
  - `packages/locale/README.md` — axis-only package
- `packages/catalog/README.md` current content covers products, categories, trees, the storefront route, and the seeder. After this task, it covers only products/categories/trees/seeder. The storefront route, layout snippet, and component overview move to `catalog-storefront/README.md`.
- `packages/catalog-scope/README.md` currently mentions `ScopedProductGridComponent` (added in P2). Remove that section and replace with a one-line cross-link to catalog-storefront-scope.
- New `catalog-storefront/README.md` covers:
  - Intro: "Public storefront for `markommerce/catalog` — HTTP controllers, Latte templates, layout glue, and theme integration."
  - Installation
  - Quick Example: a minimal `composer require markommerce/catalog markommerce/catalog-storefront`, then GET `/catalog/category/{id}` works out of the box.
  - Storefront Route section (moved from catalog)
  - Layout definition snippet (moved from catalog)
  - ProductGridComponent + ProductCard + ProductGridData table (moved from catalog, with the note about `catalog-storefront-scope` for locale-aware rendering)
  - Documentation footer
- New `catalog-storefront-scope/README.md` covers:
  - Intro: "Locale-aware product grid rendering for `markommerce/catalog-storefront` — Preference-replaces the default `ProductGridComponent` with one that resolves `Product.name`/`Product.description` through `ScopeResolver`."
  - Installation
  - Quick Example: install on top of the bridge stack; nothing else to configure (the Preference is auto-discovered).
  - How it works section: `#[Preference(replaces: ProductGridComponent::class)]`, `attachCompanion(ProductScopedOverrides)`, `ScopeResolver` in the constructor.
  - Documentation footer
- The READMEs are tested by each package's `ReadmeTest.php` (a one-line file existence + standards-conformance check). Follow the pattern from `packages/catalog/tests/Unit/ReadmeTest.php`.
- Existing tests to update (CRITICAL — the existing `packages/catalog/tests/Unit/ReadmeTest.php` contains an `it('the catalog README documents the storefront category route', …)` block (lines 33–38) that asserts the catalog README contains the literal `/catalog/category/{id}`. After this task strips the storefront route section from `packages/catalog/README.md`, that assertion fails):
  - Remove the `it('the catalog README documents the storefront category route', …)` block from `packages/catalog/tests/Unit/ReadmeTest.php`.
  - Replace it with `it('the catalog README cross-links to markommerce/catalog-storefront for the storefront route', …)` that asserts the README contains `catalog-storefront`.
  - Verify the `'## Quick Example'` and `'documents the Product and Category entities'` assertions still pass against the trimmed README (they should — those references stay).
- New ReadmeTest files for `catalog-storefront` and `catalog-storefront-scope`:
  - `packages/catalog-storefront/tests/Unit/ReadmeTest.php` asserts README contains `# markommerce/catalog-storefront`, `composer require markommerce/catalog-storefront`, `## Installation`, `## Quick Example`, `## Documentation`, `markommerce.dev/docs/packages/catalog-storefront`, and the `/catalog/category/{id}` route literal (the route lives here now).
  - `packages/catalog-storefront-scope/tests/Unit/ReadmeTest.php` asserts README contains `# markommerce/catalog-storefront-scope`, `composer require markommerce/catalog-storefront-scope`, `## Installation`, `## Quick Example`, `## Documentation`, `markommerce.dev/docs/packages/catalog-storefront-scope`, `Preference`, and `ScopedProductGridComponent`.

## Requirements (Test Descriptions)
- [ ] `it creates packages/catalog-storefront/README.md with title, intro paragraph, Installation, Quick Example, Storefront Route, Layout, and Documentation footer sections`
- [ ] `it creates packages/catalog-storefront-scope/README.md with title, intro paragraph, Installation, Quick Example, and Documentation footer sections`
- [ ] `it trims packages/catalog/README.md to remove the Storefront Route section, the layout snippet, and the ProductGridComponent overview, replacing them with a cross-link to catalog-storefront`
- [ ] `it trims packages/catalog-scope/README.md to remove the ScopedProductGridComponent section, replacing it with a cross-link to catalog-storefront-scope`
- [ ] `it adds a ReadmeTest in packages/catalog-storefront/tests/Unit/ following the existing pattern (asserts the README contains the /catalog/category/{id} storefront route literal among other markers)`
- [ ] `it adds a ReadmeTest in packages/catalog-storefront-scope/tests/Unit/ following the existing pattern (asserts the README mentions Preference and ScopedProductGridComponent)`
- [ ] `it updates packages/catalog/tests/Unit/ReadmeTest.php to drop the storefront-route assertion (line 33–38 of the existing file) and replace with a cross-link assertion to markommerce/catalog-storefront`
- [ ] `it preserves the existing P2-era ReadmeTest assertions for packages/catalog (Product, Category, seeder) and packages/catalog-scope (which does not reference ScopedProductGridComponent) after the trim`

## Acceptance Criteria
- All requirements have passing tests.
- All four READMEs follow the Package README Standards.
- The catalog-storefront README explains how to swap themes (mentioning `theme-blank`) and how the layout/component split works.
- The catalog-storefront-scope README explains the Preference mechanism and the dependency on both catalog-storefront and catalog-scope.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
