# Task 009: Docs site pages

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Add `docs/src/content/docs/packages/catalog-storefront.md` and `docs/src/content/docs/packages/catalog-storefront-scope.md`. Update `docs/src/content/docs/packages/catalog.md` to remove every storefront-coupled section (Storefront Route, Layout definition, ProductGridComponent overview, ProductCard overview) and replace each with a cross-link to catalog-storefront. Update `docs/src/content/docs/packages/catalog-scope.md` to remove the ScopedProductGridComponent section and cross-link to catalog-storefront-scope. Add `tests/Unit/Docs/CatalogStorefrontExtractPagesTest.php` mirroring `CatalogScopeDecouplePagesTest`.

## Context
- Reference: `tests/Unit/Docs/CatalogScopeDecouplePagesTest.php` (created in P2 task 013). Mirror its shape — section assertions, no `## Overview`, valid frontmatter, root-relative links.
- DOCS-STANDARDS: see `docs/DOCS-STANDARDS.md` (referenced by CLAUDE.md). Required sections for a package page: title + description frontmatter, intro paragraph, Installation, Usage, API Reference, Related Packages. No `## Overview`. Internal links must be root-relative (start with `/`).
- Existing pages to edit:
  - `docs/src/content/docs/packages/catalog.md` — currently includes the Storefront Route, Layout definition, ProductGridComponent, ProductCard, ProductCardData sections (lines ~287 onward). Strip them; cross-link to `catalog-storefront`.
  - `docs/src/content/docs/packages/catalog-scope.md` — includes a `ScopedProductGridComponent` section. Strip it; cross-link to `catalog-storefront-scope`.
- Existing tests to update (CRITICAL — `tests/Unit/Docs/CatalogScopeDecouplePagesTest.php` asserts at line 37 that `catalog-scope.md` contains the literal string `'ScopedProductGridComponent'`. Once this task strips that section, that assertion fails):
  - Remove (or relax) the `expect($content)->toContain('ScopedProductGridComponent');` line from `tests/Unit/Docs/CatalogScopeDecouplePagesTest.php`. Replace with an assertion that `catalog-scope.md` cross-links to `markommerce/catalog-storefront-scope` (the new home of `ScopedProductGridComponent`).
  - Also remove (or relax) the `expect($content)->toContain('Preference');` line (line 36) if catalog-scope's README/page no longer mentions the Preference mechanism (since the only Preference in catalog-scope was on `ScopedProductGridComponent`, now moved). Verify whether other Preference references remain in catalog-scope's page; if so, the assertion can stay.
- New pages required:
  - `catalog-storefront.md` — intro, Installation, Usage (storefront route, layout, components, template overrides), API Reference (`CategoryController`, `ProductGridComponent`, `ProductCard`, `StockBadge`, the three Data DTOs), Related Packages cross-links to catalog, layout, frontend, theme-blank, catalog-storefront-scope.
  - `catalog-storefront-scope.md` — intro, Installation, Usage (showing how the Preference triggers locale-aware rendering with no merchant config), API Reference (just `ScopedProductGridComponent`), Related Packages cross-links to catalog-storefront, catalog-scope, catalog-locale.
- Test file path: `tests/Unit/Docs/CatalogStorefrontExtractPagesTest.php`. Pattern from P2:
  ```php
  it('creates docs/src/content/docs/packages/catalog-storefront.md following DOCS-STANDARDS sectioning', function (): void {
      $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-storefront.md';
      expect(file_exists($file))->toBeTrue();
      // … same shape as CatalogScopeDecouplePagesTest
  });
  ```

## Requirements (Test Descriptions)
- [ ] `it creates docs/src/content/docs/packages/catalog-storefront.md following DOCS-STANDARDS sectioning`
- [ ] `it creates docs/src/content/docs/packages/catalog-storefront-scope.md following DOCS-STANDARDS sectioning`
- [ ] `it updates docs/src/content/docs/packages/catalog.md to remove the Storefront Route section, the Layout definition section, and the ProductGridComponent and ProductCard overviews, cross-linking to catalog-storefront instead`
- [ ] `it updates docs/src/content/docs/packages/catalog-scope.md to remove the ScopedProductGridComponent section, cross-linking to catalog-storefront-scope instead`
- [ ] `it updates tests/Unit/Docs/CatalogScopeDecouplePagesTest.php to drop the assertion that catalog-scope.md contains the ScopedProductGridComponent literal (and the related Preference literal, if no other Preference reference remains on the page), and replace with an assertion that catalog-scope.md cross-links to markommerce/catalog-storefront-scope`
- [ ] `it adds catalog-storefront.md and catalog-storefront-scope.md to the expectedPages list inside CatalogScopeDecouplePagesTest.php's docs-build smoke test (or migrate that smoke loop into the new CatalogStorefrontExtractPagesTest)`
- [ ] `it passes the docs-site build with no broken links, valid frontmatter, and no ## Overview heading on any new or edited page`
- [ ] `it ensures every internal link on the new and edited docs pages is root-relative (starts with /)`

## Acceptance Criteria
- All requirements have passing tests in `tests/Unit/Docs/CatalogStorefrontExtractPagesTest.php`.
- The docs site builds cleanly (existing `tests/Unit/Docs/*Test.php` keep passing).
- All four affected pages follow DOCS-STANDARDS.
- Code follows project standards.

## Implementation Notes
(Left blank — filled in by programmer during implementation.)
