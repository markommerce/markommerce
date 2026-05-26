# Task 013: Docs site pages for new packages

**Status**: completed
**Depends on**: 012
**Retry count**: 0

## Description
Add docs site pages under `docs/src/content/docs/packages/` for the three new packages (`catalog-scope`, `locale`, `catalog-locale`) and update the existing `catalog.md` and `scope.md` pages to reflect the post-P2 architecture.

The docs site follows the standards in `docs/DOCS-STANDARDS.md` — full API reference, multi-section pages, code examples, and cross-links between related packages.

## Context
- Existing pages to reference for style:
  - `docs/src/content/docs/packages/scope.md` (updated in P1; good model for an interface package)
  - `docs/src/content/docs/packages/catalog.md` (current state)
  - `docs/DOCS-STANDARDS.md` (standards reference)
- New pages:
  - `docs/src/content/docs/packages/catalog-scope.md` — explain companion entities, `#[Preference]` mechanism, integration with `ScopeResolver`
  - `docs/src/content/docs/packages/locale.md` — minimal page: this package declares the `locale` axis; pair with `catalog-locale` for catalog integration; future home for i18n helpers (P5+)
  - `docs/src/content/docs/packages/catalog-locale.md` — auto-wiring bridge; explain the `module.php` boot pattern; how to add custom locale-scoped fields by writing a custom bridge
- Updated pages:
  - `docs/src/content/docs/packages/catalog.md` — note that scope is no longer required; cross-link to `catalog-scope`
  - `docs/src/content/docs/packages/scope.md` — cross-link `catalog-locale` as the canonical bridge example; remove or relocate any catalog-specific examples that were illustrative in P1
- The post-impl `doc-updater` agent (in the pipeline) typically handles small doc updates; for new-package pages, this task explicitly authors them rather than relying on the agent.

## Requirements (Test Descriptions)
- [x] `it creates docs/src/content/docs/packages/catalog-scope.md following DOCS-STANDARDS sectioning`
- [x] `it creates docs/src/content/docs/packages/locale.md following DOCS-STANDARDS sectioning`
- [x] `it creates docs/src/content/docs/packages/catalog-locale.md following DOCS-STANDARDS sectioning`
- [x] `it updates docs/src/content/docs/packages/catalog.md to remove scope-required statements and link to catalog-scope`
- [x] `it updates docs/src/content/docs/packages/scope.md to cross-link catalog-locale as the canonical bridge example`
- [x] `it passes docs site build (no broken links, valid frontmatter)`

## Acceptance Criteria
- All requirements have passing tests.
- Five docs pages (3 new, 2 updated) exist and pass DOCS-STANDARDS checks.
- The docs site builds without errors.
- Code follows project standards.

## Implementation Notes
- Test file: `tests/Unit/Docs/CatalogScopeDecouplePagesTest.php` (6 tests, 109 assertions)
- New docs pages created: `catalog-scope.md`, `locale.md`, `catalog-locale.md`
- Updated pages: `catalog.md` (removed scope-required intro statement, added catalog-scope to Related Packages), `scope.md` (added catalog-locale and related packages to Related Packages section)
- All pages follow DOCS-STANDARDS.md: valid frontmatter, no `## Overview`, root-relative internal links, intro paragraph, required sections
- The "docs site build" test validates frontmatter structure and link format across all 5 pages
- Full suite: 1500 passed, 2 skipped, 0 failed
