# Task 014b: theme-blank CSS-layer bootstrap + catalog frontend scaffold

**Status**: complete
**Depends on**: 011
**Retry count**: 0

## Description
Fix the long-standing cascade-layer CSS bootstrap gap in theme-blank: today, extending `theme-blank::layout/base` (directly or via any leaf layout) loads only the components barrel from `theme-blank/resources/js/index.ts` — *not* the cascade-layer baseline, Open Props, or theme-blank's own tokens/base/layouts CSS. Move those imports into theme-blank's `index.ts` so any consumer of theme-blank's layouts gets fully-styled output for free. Trim the now-redundant explicit imports from `theme-blank-demo/resources/js/main.ts`.

Also add the minimal catalog frontend hook (`packages/catalog/package.json` + an empty `packages/catalog/resources/js/index.ts`) so the Vite scanner picks catalog up — task 015 will fill that `index.ts` with the product-card CSS import.

This task is JS/CSS + scaffolding only. The Layout PHP classes and the `{block}`→`{slot}` conversion in theme-blank's latte layouts live in task 014a (independent and can run in parallel).

## Context

### Why the CSS bootstrap belongs in theme-blank
- `packages/theme-blank/resources/views/layout/base.latte` includes `{vite('packages/theme-blank/resources/js/index.ts')}`, so any consumer that uses a theme-blank layout transitively loads `theme-blank/resources/js/index.ts`.
- Today that `index.ts` only imports the components barrel (`import './components';` + the controllers). It does **not** import the cascade-layer base CSS (`@markommerce/frontend/css/layers.css`), Open Props (`open-props/style.css`), or theme-blank's own tokens/base/layouts CSS.
- The only place those are loaded is `packages/theme-blank-demo/resources/js/main.ts`, which is demo-package-local. Any other consumer (catalog, future packages) would have to copy that bootstrap into their own `main.ts` — exactly the duplication we're avoiding by reusing the theme-blank layouts.
- Moving the cascade-layer + token bootstrap into theme-blank's `index.ts` means: extending `theme-blank::layout/base` (directly or via any leaf) gives fully-styled output. Consumers don't ship their own `main.ts` just to get tokens.

### Files to add/change

1. **Rewrite `packages/theme-blank/resources/js/index.ts`** — prepend the full cascade-layer bootstrap. The new shape mirrors what `packages/theme-blank-demo/resources/js/main.ts` currently does, minus the demo-specific parts. Final structure:
   ```ts
   // Cascade-layer baseline — must come first.
   import '@markommerce/frontend/css/layers.css';
   // Open Props raw tokens (unlayered).
   import 'open-props/style.css';
   // Theme-blank semantic tokens (inside @layer tokens).
   import '../css/tokens.css';
   // Base styles reset (inside @layer base).
   import '../css/base.css';
   // Page layout structures (inside @layer theme).
   import '../css/layouts.css';

   // Components (each imports its own component CSS).
   import './components';

   // Imperative controllers (toast/modal/drawer) — keep all the existing re-exports below.
   import { showToast as _showToast } from './toast-controller';
   /* …unchanged… */
   ```
   The existing exported helpers (`showToast`, `openModal`, `openDrawer`, types) stay untouched at the bottom.

2. **Update `packages/theme-blank/composer.json` / `package.json`** if needed:
   - Theme-blank's `package.json` already lists `open-props` as a peer dependency. Confirm a top-level `dependencies` entry is unnecessary — peer + the consumer providing it is enough for the Vite resolver. If `import 'open-props/style.css'` fails to resolve in tests after the change, add `open-props` to `dependencies` instead of (or alongside) peerDeps. Frontend test wiring already uses Vite to bundle so this is verifiable by running the JS tests.
   - `@markommerce/frontend` is also peer-only today; same note applies.

3. **Slim down `packages/theme-blank-demo/resources/js/main.ts`**: drop the now-redundant explicit CSS imports (`layers.css`, `open-props/style.css`, theme-blank `tokens.css`/`base.css`/`layouts.css`). After this task, `main.ts` becomes:
   ```ts
   import '@markommerce/theme-blank';   // pulls full bootstrap + components
   import './extensions';
   import './showcase';
   import { defineAllComponents } from '@markommerce/frontend';
   defineAllComponents();
   ```
   This is the "any consumer" recipe — and it's what catalog (task 015) implicitly inherits by using a theme-blank layout.

4. **Catalog frontend hook (small)**:
   - Add `packages/catalog/package.json` modelled on `packages/frontend-demo/package.json`: `name: "@markommerce/catalog"`, `private: true`, `markommerce.extension: "./resources/js/index.ts"`, dependency on `@markommerce/frontend` + `@markommerce/theme-blank`, peer deps on `lit` + `open-props`.
   - Add `packages/catalog/resources/js/index.ts` — empty for now (`export {};`). Task 015 will import the catalog-local product-card CSS from this file. Crucially, **no `main.ts` in catalog** — catalog doesn't bootstrap the cascade layers, theme-blank does (after this task).

### Tests to add / update
- Add a TS source-shape test in `packages/theme-blank/resources/js/index.test.ts` (or extend the existing one) asserting the new CSS imports appear at the top of `index.ts` — text-level assertion is fine; do not run the whole bundle. Reference style: `packages/theme-blank/resources/js/tokens-expanded.test.ts` and other source-shape tests in that dir.
- Add or update a TS source-shape test for `packages/theme-blank-demo/resources/js/main.ts` asserting the redundant CSS imports are gone.
- Smoke-check theme-blank-demo's showcase page still renders correctly after the main.ts trim — preferred is the existing playwright-CLS / showcase tests if they cover styling, otherwise a manual check suffices for this task and we rely on visual regression catching anything.
- Add a unit/source-shape test that catalog `package.json` has the `markommerce.extension` field pointing at `./resources/js/index.ts`.
- Add a source-shape test that catalog `resources/js/index.ts` exists and exports an empty module (`export {};` or similar).

### Project rules
- No PHP changes in this task — purely JS/CSS + package.json.

## Requirements (Test Descriptions)
- [x] `it imports the cascade-layer base CSS from theme-blank/resources/js/index.ts`
- [x] `it imports open-props, tokens, base and layouts CSS from theme-blank/resources/js/index.ts`
- [x] `it removes the redundant explicit CSS imports from theme-blank-demo/resources/js/main.ts`
- [x] `it registers the catalog package with the frontend scanner via package.json markommerce.extension`
- [x] `it provides an empty resources/js/index.ts in catalog ready for task 015's CSS import`

## Acceptance Criteria
- All requirements have passing tests
- Theme-blank-demo's showcase page still renders correctly after the main.ts trim (manual smoke test acceptable; preferred is the existing playwright-CLS or showcase tests if they cover styling)
- Catalog `package.json` + empty `index.ts` are picked up by the Vite scanner (validates via the same generated extensions manifest pattern used by frontend-demo)
- All theme-blank and catalog tests pass: `./vendor/bin/pest packages/theme-blank packages/catalog`
- JS tests pass: `pnpm test` (or whatever the JS test command is) for theme-blank and theme-blank-demo

## Notes for Implementer
- Do NOT touch the Layout PHP classes or the latte `{block}`/`{slot}` syntax in this task — task 014a owns that.
- Do NOT touch the catalog controller or the standalone `category.latte` — task 016 owns that swap.
- The catalog frontend hook (`package.json` + empty `index.ts`) is intentionally minimal here. Task 015 will start filling `index.ts` with the product-card CSS import. Keep the scaffolding in this task because tasks 015 and beyond would otherwise have to add it themselves.
- If `open-props/style.css` fails to resolve from inside theme-blank because it's only peerDep, promote it to a real `dependencies` entry in theme-blank's `package.json`. Same for `@markommerce/frontend` — if `@markommerce/frontend/css/layers.css` doesn't resolve, promote.
- Task 014a and 014b are independent and can be implemented in parallel. Tasks 015 and 016 depend on both.
