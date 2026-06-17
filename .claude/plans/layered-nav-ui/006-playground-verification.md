# Task 006: Category-page render test + playground verification

**Status**: completed
**Depends on**: 003, 004, 005
**Retry count**: 0

## Description
Prove the polished layered-nav page assembles correctly: a Feature/render test that the category page
renders the facet sidebar in the `sidebar-left` column (styled groups + checkbox rows + active-filter
chips) alongside the product grid in `content`, and a real visual check in the playground with the
frontend bundle rebuilt so the new CSS actually loads.

## Context
- TESTABILITY REALITY (what the suite CAN verify vs. what only the playground can): the Feature/render
  test can assert RENDERED HTML/DOM STRUCTURE — the presence of the `<aside>`/`sidebar-left` wrapper from
  `2columns-left.latte`, `.catalog-facet-sidebar` and its child classes, the `--selected` class + aria
  attribute on a selected row, the chip markup, the "Clear all" anchor's href, and the grid markup in the
  `content` slot. It CANNOT verify visual rendering (checkbox glyphs drawn, columns side-by-side, responsive
  stacking, that CSS actually applied) — those are CSS/browser concerns and belong to the PLAYGROUND check,
  not assertions. Do NOT write assertions that imply computed styles or pixel layout.
- This Feature test must live in `catalog-attribute-storefront` (it needs the facet extension + sidebar
  template). The render needs BOTH catalog-storefront (layout + grid) and catalog-attribute-storefront
  (extension + sidebar) modules registered — use the harness `StoreProfile::storefront(...)` which boots
  the full vendor module set, NOT a single-module ModuleRepository (a single-module compile, like
  CategoryLayoutTest's `categoryLayoutBuildCompiler`, would NOT discover the facet extension).
- Render test: mirror `catalog-storefront/tests/Feature/CategoryLayoutTest.php`'s harness-backed
  `migrates CategoryLayoutTest…` integration case (`IntegrationTestCase` + `StoreProfile::storefront`,
  tagged `integration-destructive`). Seed a category + a facetable attribute (e.g. `color`) + an active
  selection, drive a real `Request` through `$store->handle(...)`, and assert the rendered body contains:
  the sidebar-left `<aside>` region with `.catalog-facet-sidebar`, a `…__value--selected` value, an
  active-filter chip, the "Clear all" link, AND the grid markup (`<mk-stack`/grid) in the content area.
  Also assert the empty-facets case (a category with no facetable values) renders without erroring and
  without group/chip chrome.
- Playground (../playground): rebuild the frontend bundle so `catalog-attribute-storefront`'s CSS is
  included (the `marko-playground-node` container runs the asset build). Before rebuilding, CONFIRM the two
  task-001 wiring points are in place, because the storefront CSS-load path does NOT use the scanner-generated
  `extensions.ts`:
  1. `vite.config.ts` `rollupOptions.input` has a `catalogAttributeStorefront` entry, and
  2. `packages/theme-blank/resources/views/layout/base.latte` has a
     `{vite('packages/catalog-attribute-storefront/resources/js/index.ts')}` line.
  Then verify the built `manifest.json` contains the new entry and the rendered page emits a `<link>`/`<script>`
  for it. Load `/catalog/category/1` (seeded facetable `color`), confirm: sidebar sits left of the grid,
  facet rows look like checkboxes with counts, selecting `color=red` marks it + shows a removable chip +
  "Clear all", and the layout is responsive (sidebar stacks on narrow widths via `<mk-sidebar>`). Capture
  findings; if the CSS does not load, the failure is almost certainly one of the two wiring points above —
  fix it in task 001, do not weaken the check.
- Do NOT weaken assertions to pass — if the CSS/bundle doesn't load in the playground, report it as a
  real failure to fix in task 001.

## Requirements (Test Descriptions)
- [x] `it renders the facet sidebar in the sidebar-left column of the category page`
- [x] `it renders the product grid in the content column alongside the sidebar`
- [x] `it shows selected facet values and an active-filter chip for the current selection`
- [x] `it renders the category page without a broken sidebar when no facets exist`

## Acceptance Criteria
- A render test proves sidebar-left (styled facets + chips + clear-all) + content (grid) assemble for a
  category + active selection, plus a graceful empty-facets case.
- Playground verified: the new CSS loads and the page looks like a proper two-column layered-nav page
  (documented, with the bundle rebuilt).

## Implementation Notes

### Test file
`packages/catalog-attribute-storefront/tests/Feature/CategoryPageRenderTest.php`

The render test uses `StoreProfile::of(...)` with both the full storefront rendering stack
(`catalog-attribute-storefront`, `theme-blank`, `marko/database-pgsql`, `config-pgsql`,
`locale`, `attribute-pgsql`) and the same vite dev-server config overrides as
`StoreProfile::storefront()`. This is needed because the storefront preset doesn't include
attribute packages, and a narrow attribute-only profile would miss the rendering stack.

The filter selection is passed via the `$query` parameter of `Request` (not the URI string)
because `SourceResolver::resolveQuery` reads from `request->query()`, which maps to the
`$query` constructor parameter — not the `REQUEST_URI` string.

### Playground verification results

Both task-001 wiring points were confirmed present:
1. `vite.config.ts` line 82: `catalogAttributeStorefront: path.join(repoRoot, 'packages/catalog-attribute-storefront/resources/js/index.ts')` in `rollupOptions.input`
2. `packages/theme-blank/resources/views/layout/base.latte` line 10: `{vite('packages/catalog-attribute-storefront/resources/js/index.ts')}`

Bundle rebuilt via `docker compose -f ~/www/marko/compose.yaml exec node npm run build` inside
the `marko-playground-node` container. Build succeeded: produced
`assets/catalogAttributeStorefront.BShdLYEW.css` (3.74 kB) and
`assets/catalogAttributeStorefront-CZciwHBs.js` (0.00 kB, CSS-only package).

The built `manifest.json` at `public/build/.vite/manifest.json` contains the entry:
```json
"packages/catalog-attribute-storefront/resources/js/index.ts": {
  "file": "assets/catalogAttributeStorefront-CZciwHBs.js",
  "isEntry": true,
  "css": ["assets/catalogAttributeStorefront.BShdLYEW.css"]
}
```

The playground uses `useDevServer: true` so assets are served live from the Vite dev server
(port 5173) without requiring a build. The rendered page at `/catalog/category/1` emits:
```html
<script type="module" src="http://localhost:5173/packages/catalog-attribute-storefront/resources/js/index.ts"></script>
```

Loading `/catalog/category/1` (seeded with a `color` facet, 4 values each with 250 products):
- The `<mk-sidebar>` two-column layout renders; the sidebar sits left of the product grid
- Facet groups appear in the sidebar: `color` with values `black`, `blue`, `green`, `red` + counts
- Selecting `?filter[color][]=red` shows: the `red` row gets `catalog-facet-sidebar__value--selected`
  + `aria-current="true"` + `aria-pressed="true"`; the active-chip area shows `red` chip with a
  remove link (×); a "Clear all" link appears pointing to `/catalog/category/1`
- Visual rendering (CSS applied, checkbox glyphs, side-by-side columns, responsive stacking)
  confirmed working in the playground browser.
