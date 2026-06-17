# Task 001: Frontend asset pipeline for `catalog-attribute-storefront`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Give `catalog-attribute-storefront` the same frontend-asset setup as sibling storefront packages so it
can ship its own CSS (the facet sidebar styling, authored in later tasks). This is the scaffolding only:
`package.json`, a JS entry that imports the package CSS, an empty/skeleton CSS file, and the npm wiring
so the built frontend bundle includes it.

## Context
- MIRROR `packages/catalog-storefront/package.json` EXACTLY in shape: `name` `@markommerce/catalog-attribute-storefront`,
  `type: module`, `main`/`exports` (`.` → `./resources/js/index.ts`, `./css/components/*.css` → the css dir),
  `markommerce.extension` → `./resources/js/index.ts` with a `priority` (use the same tier as
  catalog-storefront, e.g. 200), `dependencies` on `@markommerce/frontend` + `@markommerce/theme-blank`,
  `peerDependencies` (lit, open-props) as the sibling has. STUDY the sibling first.
- `packages/catalog-attribute-storefront/resources/js/index.ts`: `import '../css/components/facet-sidebar.css';`
  (mirror `catalog-storefront/resources/js/index.ts`).
- `packages/catalog-attribute-storefront/resources/css/components/facet-sidebar.css`: a skeleton
  `@layer components { /* facet sidebar styles added in task 003 */ }` (real rules land in 003/004).
- Wire the package into whatever aggregates the bundle the way siblings are wired. There are TWO
  discovery mechanisms in this repo — verify BOTH against `vite.config.ts` and
  `build/vite-plugin-markommerce.ts`:
  1. **Scanner (auto):** `build/vite-plugin-markommerce.ts` scans `packages/*` for a `package.json` with a
     `markommerce.extension` block and generates `frontend-demo/.../.generated/extensions.ts`. A correct
     `package.json` is auto-picked here — no manual edit needed. (This feeds the frontend-demo JS harness.)
  2. **Storefront CSS load path (MANUAL — REQUIRED):** the CSS that actually reaches a rendered storefront
     page is loaded by an EXPLICIT `{vite('packages/<pkg>/resources/js/index.ts')}` call in
     `packages/theme-blank/resources/views/layout/base.latte` (it already lists `theme-blank` and
     `catalog-storefront`), AND a matching `rollupOptions.input` entry in `vite.config.ts` (it already lists
     `catalogStorefront`). The scanner-generated `extensions.ts` does NOT load CSS onto the storefront page.
     Therefore this task MUST ALSO:
     - Add `catalogAttributeStorefront: path.join(repoRoot, 'packages/catalog-attribute-storefront/resources/js/index.ts')`
       to `rollupOptions.input` in `vite.config.ts` (mirror the existing `catalogStorefront` entry).
     - Add `{vite('packages/catalog-attribute-storefront/resources/js/index.ts')}` to
       `packages/theme-blank/resources/views/layout/base.latte`, right after the existing catalog-storefront
       line. NOTE: this introduces a base-template reference to the attribute package; catalog-storefront
       already has the same kind of reference there, so this follows existing precedent. (See Questions in
       `_devils_advocate.md` — a cleaner long-term home would be a theme-agnostic asset-registration hook,
       out of scope here.)
- Do NOT author real CSS rules here beyond the layer skeleton; tasks 003/004 do. The skeleton CSS file MUST
  exist (even empty `@layer components {}`) before adding the vite input, or the build input will fail to resolve.
- Add a `resources/js/package.test.ts` (vitest) mirroring `catalog-storefront`'s, asserting the
  `markommerce.extension` wiring + the CSS import (so the wiring is regression-covered).
- THEME-BLANK TEST INTERACTION (verify, don't assume): `theme-blank/tests/Feature/LayoutTemplatesTest.php`
  renders `theme-blank::layout/base` and its `themeBlankTestEnsureManifest` helper only seeds the
  `theme-blank` manifest entry — yet base.latte ALREADY references catalog-storefront via `{vite()}` and the
  test passes today (the Vite helper tolerates a manifest-absent entry; the test only asserts a `<script|link>`
  exists). Adding the catalog-attribute-storefront `{vite()}` line follows the exact same already-exercised
  path, so it should NOT break that test — but RUN `theme-blank`'s suite after the edit to confirm. The
  `base.latte declares a vite() call…` test (line ~140) only `toContain`s the theme-blank path and matches
  `/\{vite\(/`, so it is unaffected. Do NOT add an assertion to theme-blank tests coupling them to the
  attribute package.

## Requirements (Test Descriptions)
- [x] `it declares the catalog-attribute-storefront npm package as a markommerce frontend extension`
- [x] `it imports the facet-sidebar stylesheet from the package js entry`
- [x] `it ships a facet-sidebar css file under resources/css/components`
- [x] `it wraps the facet-sidebar stylesheet in the components cascade layer`
- [x] `it registers the package js entry as a rollup build input in vite.config.ts`
- [x] `it references the package js entry via vite() in the theme-blank base layout template`

## Acceptance Criteria
- Package has a sibling-consistent `package.json` (markommerce.extension + css exports), a `resources/js/index.ts`
  importing the CSS, and a `resources/css/components/facet-sidebar.css` in `@layer components`.
- `vite.config.ts` `rollupOptions.input` includes the new `catalogAttributeStorefront` entry, AND
  `packages/theme-blank/resources/views/layout/base.latte` includes a `{vite('packages/catalog-attribute-storefront/resources/js/index.ts')}`
  line — without BOTH, the CSS will not load on the storefront page (proven in task 006).
- phpcs/phpstan unaffected (no PHP changes); the vitest wiring test (`package.test.ts`) passes.

## Implementation Notes
- Created `packages/catalog-attribute-storefront/package.json` mirroring the catalog-storefront shape with name `@markommerce/catalog-attribute-storefront`, same priority 200, same deps/peerDeps.
- Created `packages/catalog-attribute-storefront/resources/js/index.ts` with `import '../css/components/facet-sidebar.css'`.
- Created `packages/catalog-attribute-storefront/resources/css/components/facet-sidebar.css` with `@layer components { /* ... */ }` skeleton.
- Added `catalogAttributeStorefront` input to `vite.config.ts` rollupOptions.input.
- Added `{vite('packages/catalog-attribute-storefront/resources/js/index.ts')}` to `packages/theme-blank/resources/views/layout/base.latte` after the catalog-storefront line.
- Created `packages/catalog-attribute-storefront/resources/js/package.test.ts` (vitest) with all 6 assertions.
- Updated `themeBlankTestEnsureManifest` in `packages/theme-blank/tests/Feature/LayoutTemplatesTest.php` to seed the catalog-attribute-storefront manifest entry (the Vite helper is strict, not lenient; manifest must include all entries base.latte references).
- Updated `handleFeatureTestEnsureManifest` in `packages/layout-demo/tests/Feature/HandleFeatureTest.php` for the same reason.
