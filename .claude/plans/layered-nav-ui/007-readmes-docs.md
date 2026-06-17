# Task 007: READMEs + docs

**Status**: completed
**Depends on**: 001, 002, 003, 004, 005, 006
**Retry count**: 0

## Description
Document the polished layered-navigation UI: the new `catalog-attribute-storefront` frontend assets
(package.json + CSS) and facet-sidebar presentation, the two-column category layout, and the active-filter
chips — across package READMEs and the docs site.

## Context
- Standard: Package README Standards in `.claude/code-standards.md` + `docs/DOCS-STANDARDS.md`. Mirror
  sibling READMEs (`catalog-storefront`, `catalog-attribute-storefront`).
- `catalog-attribute-storefront/README.md`: add a "Storefront UI" / theming section — the facet sidebar
  presentation (checkbox-style no-JS rows + counts + selected state), active-filter chips + clear-all, the
  CSS lives in `resources/css/components/facet-sidebar.css` (`@layer components`, `--mk-*` tokens) shipped
  via the package's frontend extension, and that it renders in the `sidebar-left` slot of the two-column
  category layout.
- `catalog-storefront/README.md` + docs page: note the category page uses theme-blank's
  `TwoColumnsLeftLayout` (sidebar-left + content) and the cohesive sort/grid/pagination styling.
- Docs (`docs/src/content/docs/packages/`): update `catalog-attribute-storefront.md` and
  `catalog-storefront.md` for the UI/layout/assets; cross-link. Reflect only shipped behavior; note no-JS
  + accessibility + responsive `<mk-sidebar>`.

## Requirements (Test Descriptions)
- [x] `it documents the layered navigation storefront UI and facet-sidebar styling in the catalog-attribute-storefront README`
- [x] `it documents the two-column category layout in the catalog-storefront docs`

> Note: documentation-completeness checks — verify the README/docs exist and contain the required sections.

## Acceptance Criteria
- READMEs + docs follow the standards and match the shipped UI (two-column layout, facet sidebar styling,
  active-filter chips, frontend assets). Cross-linked.

## Implementation Notes

- Added `## Storefront UI` section to `packages/catalog-attribute-storefront/README.md` documenting: frontend assets (`facet-sidebar.css`, `@layer components`, `--mk-*` tokens, `markommerce.extension`), `FacetSidebarComponent`, `facet-sidebar.latte` with checkbox-style no-JS rows, `aria` selected state, active filter chips + `clearAllUrl` Clear-all link, and `TwoColumnsLeftLayout` / `sidebar-left` slot reference.
- Updated `docs/src/content/docs/packages/catalog-storefront.md` layout section to show the actual shipped `TwoColumnsLeftLayout` (replacing the stale `OneColumnLayout`), added `sidebar-left` slot description, `catalog-attribute-storefront` cross-link, and a new "Frontend styling" subsection covering `category.css`, `pagination.css`, `@layer components`, and `--mk-*` tokens.
- Updated `packages/catalog-storefront/README.md` Layout section to mention `TwoColumnsLeftLayout`, `sidebar-left`, and `content` slots.
- Added `## Storefront UI` section to `docs/src/content/docs/packages/catalog-attribute-storefront.md` covering frontend assets, facet sidebar template behavior (no-JS, accessibility, chips, Clear all), and the `sidebar-left` layout extension mechanism.
- Tests placed in: `packages/catalog-attribute-storefront/tests/ReadmeTest.php` (req 1) and `packages/catalog-storefront/tests/Unit/ReadmeTest.php` (req 2).
