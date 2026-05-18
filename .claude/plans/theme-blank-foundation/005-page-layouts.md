# Task 005: Page Latte Layouts + `layouts.css`

**Status**: completed
**Depends on**: 003, 004
**Retry count**: 0

**Dependency note:** depends on 004 (not just 003) because both tasks mutate the same `packages/frontend-demo/resources/js/main.ts` and the same `packages/frontend-demo/resources/js/package.test.ts` import-order assertion. Serializing through 004 avoids a merge conflict and lets this task's `layouts.css` import slot in cleanly after `base.css`.

## Description
Ship the five page-layout Latte templates that Magento's `Magento_Theme` blank theme covers: `base.latte` (top-level skeleton), `empty.latte` (minimal body-only), `1column.latte` (single column), `2columns-left.latte` and `2columns-right.latte` (sidebar + main), and `3columns.latte` (two sidebars + main). Ship the CSS for the column structure in `resources/css/layouts.css` inside `@layer theme`.

## Context
- Template directory: `packages/theme-blank/resources/views/layout/`
- CSS file: `packages/theme-blank/resources/css/layouts.css`
- **Latte template resolution convention (confirmed by reading `marko/packages/view/src/ModuleTemplateResolver.php` and `marko/packages/view-latte/src/ModuleLoader.php`).** Templates are addressed as `{module-short-name}::{path/without/extension}`. Example: the existing demo's `DemoLayout` uses `template: 'frontend-demo::layout/base'`. For this package the short module name derived from `markommerce/theme-blank` is `theme-blank`, so the canonical template handles are `theme-blank::layout/base`, `theme-blank::layout/empty`, etc. **The `@theme-blank/layout/base.latte` syntax mentioned in earlier plan drafts is wrong** — Latte's `{layout}` / `{include}` / `{extends}` directives go through `ModuleLoader::getReferredName()` which throws a `RuntimeException` unless the name contains `::`. Use the `::` form throughout.
- **Latte template structure:**
  - `base.latte` — `<!doctype html>`, `<html lang="en">`, `<head>` with `<meta charset>`, `<meta viewport>`, `<title>{block title}Markommerce{/block}</title>`, `{block head-extra}{/block}`, `{vite('packages/theme-blank/resources/js/index.ts')}`. `<body>` contains `{block body}<header>{block header}{/block}</header><main>{block main}{/block}</main><footer>{block footer}{/block}</footer>{/block}`. Every block has a default empty body so consumers may override or skip.
  - **Block-redefinition note (Latte semantics).** Latte child templates that `{layout 'parent'}` may redefine any block declared in the parent. A redefined `{block body}` in `empty.latte` discards the parent's `body` content entirely — including the parent's `{block header}` / `{block main}` / `{block footer}` definitions — and substitutes its own. That is the intended Phase 1 behavior: `empty.latte` deliberately strips the chrome and exposes only `{block content}{/block}`. Document this in the template comments so consumers don't expect to fill `header`/`footer` when using `empty.latte`.
  - `empty.latte` — `{layout 'theme-blank::layout/base'}` then redefines `{block body}` to expose only `{block content}{/block}` with no header/main/footer wrapper.
  - `1column.latte` — `{layout 'theme-blank::layout/base'}`, redefines `{block main}` to a `.mk-layout-1col` wrapper with `{block content}{/block}`.
  - `2columns-left.latte` — `{layout 'theme-blank::layout/base'}`, redefines `{block main}` to a `.mk-layout-2col-left` wrapper with `<aside>{block sidebar-left}{/block}</aside>{block content}{/block}`.
  - `2columns-right.latte` — mirror of `2columns-left.latte` with sidebar on the right (`{block sidebar-right}{/block}` block name).
  - `3columns.latte` — `{layout 'theme-blank::layout/base'}`, redefines `{block main}` to a `.mk-layout-3col` wrapper with `<aside>{block sidebar-left}{/block}</aside>{block content}{/block}<aside>{block sidebar-right}{/block}</aside>`.
- **`layouts.css`** (inside `@layer theme`):
  - `.mk-layout-1col { max-width: 1280px; margin-inline: auto; padding: var(--mk-space-4); }` (container behavior)
  - `.mk-layout-2col-left, .mk-layout-2col-right { display: grid; gap: var(--mk-space-4); max-width: 1280px; margin-inline: auto; padding: var(--mk-space-4); }` with `grid-template-columns: 240px 1fr` (or right-mirrored) at `@media (--mk-breakpoint-md)`, stacked single-column below
  - `.mk-layout-3col` similarly with `grid-template-columns: 240px 1fr 240px` at `@media (--mk-breakpoint-lg)`
- Export `./css/layouts.css` from `package.json` (task 001 already declared this in exports).
- Import the new layouts.css in `frontend-demo/resources/js/main.ts` after `base.css` so the demo gets layout styling available. Update demo's `package.test.ts` accordingly.
- The demo currently has its own `DemoLayout` (`packages/frontend-demo/src/Layout/DemoLayout.php`) that uses its own `base.latte`. Don't touch that — `frontend-demo` keeps its own layout. The new theme-blank layouts are available for downstream consumers but `frontend-demo` doesn't need to migrate.

## Requirements (Test Descriptions)
- [ ] `it ships base.latte at resources/views/layout/base.latte`
- [ ] `base.latte declares <!doctype html> and <html lang attribute>`
- [ ] `base.latte renders a vite() call pointing at packages/theme-blank/resources/js/index.ts`
- [ ] `base.latte exposes title, head-extra, body, header, main, footer blocks`
- [ ] `empty.latte extends base.latte and exposes only a content block via body`
- [ ] `1column.latte extends base.latte, wraps main in .mk-layout-1col, exposes a content block`
- [ ] `2columns-left.latte extends base.latte, wraps main in .mk-layout-2col-left, exposes sidebar-left and content blocks`
- [ ] `2columns-right.latte extends base.latte, wraps main in .mk-layout-2col-right, exposes sidebar-right and content blocks`
- [ ] `3columns.latte extends base.latte, wraps main in .mk-layout-3col, exposes sidebar-left, sidebar-right, and content blocks`
- [ ] `layouts.css wraps all rules in @layer theme`
- [ ] `layouts.css defines .mk-layout-1col with max-width and centered margins`
- [ ] `layouts.css defines responsive grid columns for .mk-layout-2col-left/.mk-layout-2col-right using --mk-breakpoint-md`
- [ ] `layouts.css defines responsive grid columns for .mk-layout-3col using --mk-breakpoint-lg`
- [ ] `it is exported from theme-blank package.json so import '@markommerce/theme-blank/css/layouts.css' resolves`
- [ ] `each layout template compiles successfully through the Latte engine (Feature test wires up a real LatteEngine via MarkommerceLatteEngineFactory + ModuleLoader + ModuleTemplateResolver, registers theme-blank as a module in a ModuleRepository, then renders each template — base, empty, 1column, 2columns-left, 2columns-right, 3columns — and asserts the render returns a non-empty string without throwing. This proves the {layout 'theme-blank::layout/base'} directives resolve through ModuleLoader::getReferredName() correctly.)`
- [ ] `the {vite()} call in base.latte renders successfully when the engine has the ViteExtension registered (Feature test seeds a fake Vite manifest mirroring the pattern in DemoControllerTest::demoTestEnsureManifest)`
- [ ] `the frontend-demo main.ts imports @markommerce/theme-blank/css/layouts.css after base.css`

## Acceptance Criteria
- All requirements have passing tests
- `composer test` (full Pest suite) passes
- `npm run test` passes
- `npm run lint:css` passes
- All five layouts compile through Latte without errors

## Implementation Notes
(Left blank — filled in by programmer during implementation)
