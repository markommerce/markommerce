# Plan: theme-blank Foundation

## Created
2026-05-15

## Status
completed

## Objective
Stand up the `markommerce/theme-blank` package — the design-system foundation for the Magento-style blank theme. Phase 1 ships the package scaffold, the `--mk-*` semantic design token set (migrated and expanded from `packages/frontend`), base CSS, five Latte page layouts, JS API stubs for `showToast`/`openModal`, the docs index, and Playwright-backed CLS smoke-test infrastructure — but **no `<mk-*>` component implementations** (those land in Phases 2–5).

## Related Issues
none

## Discovery Notes

**Existing frontend foundation:**
- `packages/frontend/resources/js/registry.ts` provides the Lit + mixin-chain custom-element registry.
- `packages/frontend/resources/css/layers.css` declares the cascade-layer order: `reset, tokens, base, components, modules, theme, utilities`.
- `packages/frontend/src/View/Latte/ViteExtension.php` exposes `{vite('entry')}` to Latte templates.
- `packages/frontend/src/View/Latte/MarkommerceLatteEngineFactory.php` is a `#[Preference]` for `LatteEngineFactory`, wiring `SlotExtension` + `ViteExtension`.
- `packages/frontend-demo/` proves the pattern end-to-end with `<markommerce-counter>` (light-DOM Lit element).
- The Vite plugin at `build/vite-plugin-markommerce` auto-discovers packages with `"markommerce": { "extension": "..." }` in `package.json` and stitches them into `.generated/extensions.ts`. **`theme-blank` is automatically picked up** once its `package.json` declares the extension entry.

**Critical conflict resolved during clarification (decision: Move + rename):**
- `packages/frontend/resources/css/tokens.css` *already exists* and ships unprefixed semantic tokens (`--color-primary`, `--space-1`, `--radius-base`, etc.) referencing Open Props raw vars.
- The brief calls for `--mk-*` prefixed tokens in `theme-blank`.
- **Decision:** migrate the existing token set out of `frontend` into `theme-blank`, renaming to `--mk-*`. `frontend` becomes a pure engine (layers + registry + Latte/Vite glue) with no design tokens of its own. The `frontend` `layers.css` stays — it's framework-level, not theme-level.
- Token migration touches: `frontend/resources/css/tokens.css` (delete), `frontend/package.json` (drop `./css/tokens.css` export), `frontend/resources/js/layers.test.ts` (strip token assertions), `frontend-demo/resources/js/main.ts` (re-point import), `frontend-demo/resources/js/package.test.ts` (re-point assertions), `frontend-demo/resources/css/components/counter.css` (rename CSS-var references), `frontend-demo/tests/Feature/DemoControllerTest.php` (re-point assertion strings), `frontend/resources/css/layers.css` (header comment mentions `--color-primary`).

**Docs (decision: subdirectory + index.md only):**
- The docs site has *no Astro/Starlight installation yet* — only markdown content under `docs/src/content/docs/`. There is no sidebar config to edit.
- Existing package docs use a flat pattern (`docs/src/content/docs/packages/frontend.md`).
- **Decision:** create the subdirectory pattern now (`docs/src/content/docs/packages/theme-blank/index.md`) so Phase 2–5 can drop in `mk-button.md` etc. without migrating. Sidebar wiring is deferred until Starlight is set up.

**CLS testing (decision: Install Playwright + smoke test):**
- No browser-runner exists in the repo today. JS tests use Vitest + happy-dom.
- **Decision:** install Playwright in Phase 1 as a dev dep; write a smoke test that boots a `base.latte` page, observes `layout-shift` `PerformanceObserver` entries, and asserts CLS === 0. Phases 2–5 add fixtures to that test as components arrive.

**Test patterns to follow:**
- Composer manifest test: `packages/frontend/tests/Unit/ComposerManifestTest.php`
- NPM manifest test: `packages/frontend/tests/Unit/NpmPackageTest.php`
- TS config test: `packages/frontend/tests/Unit/TypescriptConfigTest.php`
- Linting config test: `packages/frontend/tests/Unit/LintingConfigTest.php`
- Module boot test: `packages/frontend/tests/Feature/ModuleBootTest.php`
- CSS layers test: `packages/frontend/resources/js/layers.test.ts`

## Scope

### In Scope
- `packages/theme-blank/` Composer + npm package scaffold (`composer.json`, `package.json`, `module.php`, `tsconfig.json`, dir structure, manifest tests)
- Migration of existing tokens from `packages/frontend` → `packages/theme-blank` with `--mk-*` rename
- Expanded `--mk-*` token set (success/warning/danger/info colors, full typography scale, spacing 0–9, shadows, motion easings, breakpoints)
- `resources/css/base.css` inside `@layer base` (minimal reset + heading defaults referencing the new tokens)
- Five Latte page layouts: `base.latte`, `empty.latte`, `1column.latte`, `2columns-left.latte`, `2columns-right.latte`, `3columns.latte` + `resources/css/layouts.css` inside `@layer theme`
- JS entry `resources/js/index.ts` exporting `showToast`/`openModal` stub functions plus `ToastOptions`/`ModalOptions` types
- Playwright dev dependency + CLS smoke test (`tests/Browser/`) that boots a Latte page and asserts CLS === 0
- Docs page `docs/src/content/docs/packages/theme-blank/index.md` (full reference per `docs/DOCS-STANDARDS.md`, including tokens table, JS API, layouts, extension guide, and CLS principle)
- Slim package `README.md` per `docs/DOCS-STANDARDS.md` README format

### Out of Scope (deferred to Phases 2–5)
- Any `<mk-*>` component implementations
- Real `showToast` / `openModal` controller behavior (Phase 4)
- Per-component docs pages
- Sidebar/Astro Starlight installation
- Live demo package `theme-blank-demo`
- Icons

## Success Criteria
- [ ] `packages/theme-blank/` exists with passing composer + npm + tsconfig + linting manifest tests
- [ ] Root `composer.json` requires `markommerce/theme-blank` at `self.version`
- [ ] `--mk-*` token set covers all categories listed in the brief and is inside `@layer tokens`
- [ ] `packages/frontend/resources/css/tokens.css` no longer exists; `frontend` exports only `layers.css`
- [ ] `frontend-demo` continues to render and all its existing tests pass with the renamed token references
- [ ] All five page layouts render and expose the documented `{block}` regions
- [ ] `showToast` and `openModal` are exported with documented signatures and produce a stub warning when called
- [ ] CLS smoke test runs via `npm run test:cls` and asserts CLS === 0 against the `1column.latte`-shaped fixture (Playwright is host-side only; not wired into the PHP-only `composer test:all` because that runs inside an Alpine PHP container with no Node/Chromium)
- [ ] `docs/src/content/docs/packages/theme-blank/index.md` is complete per `docs/DOCS-STANDARDS.md`
- [ ] Slim `packages/theme-blank/README.md` matches the README format
- [ ] `composer test` and `composer test:all` (both PHP-only) pass; `npm run test` (Vitest) passes; `npm run test:cls` (Playwright) passes
- [ ] PHPStan level 8 clean, PHP-CS-Fixer clean, ESLint + stylelint clean
- [ ] No `final` classes; every `throw` has a `@throws` tag; constructor-injection only
- [ ] Root `tsconfig.json` and `vite.config.ts` both register `@markommerce/theme-blank` aliases so consumer imports resolve under both tsc and Vite

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffold + manifests | – | pending |
| 002 | Migrate tokens from `frontend` to `theme-blank` with `--mk-*` rename | 001 | pending |
| 003 | Expand `--mk-*` token set with new categories | 002 | pending |
| 004 | Base styles (`base.css`) | 003 | pending |
| 005 | Page Latte layouts + `layouts.css` | 003, 004 | pending |
| 006 | JS entry with `showToast`/`openModal` stubs | 001 | pending |
| 007 | Playwright + CLS smoke test | 005 | pending |
| 008 | Docs index page | 002, 003, 004, 005, 006, 007 | pending |
| 009 | Slim package README | 008 | pending |

## Architecture Notes

**Cascade-layer contract:** `theme-blank` contributes to the `tokens`, `base`, and `theme` layers (the latter from `layouts.css`). It does **not** contribute to `components` in Phase 1 — that layer is reserved for the component CSS that arrives in Phases 2–5. `frontend` keeps `layers.css` as the framework's layer-order declaration.

**Token namespace:** all theme-blank tokens are `--mk-*` prefixed. Open Props raw vars (`--blue-6`, `--size-3`, …) remain referenced inside the token-mapping layer, but consumers should always reach for the `--mk-*` aliases. Dark-mode rebind support via `[data-theme="dark"]` (carry over from the existing tokens.css pattern).

**Vite extension auto-discovery:** `theme-blank`'s `package.json` declares `"markommerce": { "extension": "./resources/js/index.ts", "priority": 100 }`. The existing `markommerceModuleScanner` plugin (referenced from `vite.config.ts`) picks it up automatically — no `vite.config.ts` changes needed.

**Latte layout resolution:** templates live under `resources/views/layout/*.latte`. The `ModuleLoader` + `ModuleTemplateResolver` pair (wired in by `MarkommerceLatteEngineFactory`) resolves templates addressed as `{module-short-name}::{path/without/extension}`. For `markommerce/theme-blank` the short name is `theme-blank`, so the canonical handles are `theme-blank::layout/base`, `theme-blank::layout/empty`, `theme-blank::layout/1column`, `theme-blank::layout/2columns-left`, `theme-blank::layout/2columns-right`, `theme-blank::layout/3columns`. `base.latte` is the root; the other five use `{layout 'theme-blank::layout/base'}`. (The earlier `@theme-blank/...` syntax was incorrect — `ModuleLoader::getReferredName()` throws unless the template name contains `::`.)

**CLS-prevention principle (documented in task 008, codified in tasks 004 + Phase 2 onwards):**
> Every component must render correctly without JavaScript. The Lit class is a behavior layer on top of canonical server-rendered HTML. Components style themselves via plain CSS targeting the custom-element selector inside `@layer components`. JS does not replace children; it augments behavior and binds events.

The `:not(:defined)` safety-net pattern is documented in `base.css` as a convention; the actual rule is empty in Phase 1 (no `mk-*` elements exist yet) but will be expanded in Phase 2 once components arrive.

**TDD discipline:** every task is Red-Green-Refactor. Tests live in `packages/theme-blank/tests/Unit/`, `tests/Feature/`, `tests/Browser/`, and Vitest tests live alongside the source in `resources/js/`.

## Risks & Mitigations
- **Token rename breaks `frontend-demo`** → task 002 explicitly updates every consumer (`main.ts`, `package.test.ts` including its ordering assertions on lines 86/95, `counter.css`, `DemoControllerTest.php` including the `strpos`-vs-`false` type-juggling trap on lines 380–381, `layers.test.ts` including the module-scope `readFileSync(tokens.css)` load); CI catches regressions.
- **Stale `--color-text` reference in `counter.css`** → already broken in the current code (the var is never defined); task 002 migrates it to `--mk-color-on-surface`, fixing the dangling reference in the same pass.
- **Playwright is heavy** → it's a one-time install; the smoke test is small and runs via `npm run test:cls` from the host. It is **NOT** wired into `composer test:all` because that script executes inside a PHP-only Alpine container (`marko-playground-app`) with no Node/Chromium toolchain.
- **Latte layout resolution convention** → confirmed via reading `marko/packages/view-latte/src/ModuleLoader.php` and `marko/packages/view/src/ModuleTemplateResolver.php`: handles use `module-short-name::path/without/extension` form. Pinned in `_plan.md`'s Architecture Notes and in task 005.
- **Open Props `--size-0` does not exist** → task 003 pins `--mk-space-0` to literal `0` (semantic "no space") rather than mapping to a nonexistent Open Props var. The 1–9 range maps cleanly to `--size-1`..`--size-9`.
- **TypeScript and Vite alias coverage for `@markommerce/theme-blank`** → task 001 adds both the `tsconfig.json` `paths` entries and the `vite.config.ts` `resolve.alias` entries before task 002 introduces the first consumer import.
- **Sidebar wiring deferred** → noted in `_plan.md` and the docs page; revisit when Starlight infra is set up. Doesn't block Phase 1.
- **`base.css` `:not(:defined)` is empty in Phase 1** → the convention is documented in a comment so Phase 2 doesn't reinvent it; the docs page describes the rule.
