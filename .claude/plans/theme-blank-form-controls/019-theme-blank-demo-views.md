# Task 019: Latte Views — `base.latte` + `showcase.latte`

**Status**: completed
**Depends on**: 018
**Retry count**: 0

## Description
Create the two Latte templates for the new package: the base HTML scaffold (`layout/base.latte`) that loads the Vite bundle, and the showcase view (`showcase.latte`) that renders all 12 Phase 2 primitives and all 10 Phase 3 form controls. The content for `showcase.latte` is *moved* from `packages/frontend-demo/resources/views/counter.latte` (the move-out happens in task 021; this task only *creates* the new copy).

## Context
- Related files (CREATE):
  - `packages/theme-blank-demo/resources/views/layout/base.latte`
  - `packages/theme-blank-demo/resources/views/showcase.latte`
- Reference:
  - `packages/frontend-demo/resources/views/layout/base.latte`
  - `packages/frontend-demo/resources/views/counter.latte`
- The `{vite()}` call in base.latte must reference the new bundle entry: `packages/theme-blank-demo/resources/js/main.ts` (the actual main.ts file is created in task 020).
- The Latte `{syntax off}…{/syntax}` wrapper around the inline `<script>` block (the mk-form event listener demo) must be preserved verbatim — Latte parses `{...}` as expressions.

## `base.latte` Shape

Mirror `frontend-demo/resources/views/layout/base.latte`. Change:
- `{vite(...)}` path → `'packages/theme-blank-demo/resources/js/main.ts'`
- `<title>` → "Markommerce — theme-blank showcase"
- Remove the bottom `<script>` block that listens for `markommerce:counter:changed` (that event belongs to frontend-demo). Replace it with a minimal:

```html
<script>
  document.addEventListener('mk-submit', (e) => {
    console.log('[mk-submit]', e.detail);
  });
  document.addEventListener('mk-invalid', (e) => {
    console.log('[mk-invalid]', e.detail);
  });
</script>
```

(This bubble-listener replaces the per-`mk-form` listener that lived in the old `counter.latte`. Bubble events are simpler at the layout level since `mk-submit` and `mk-invalid` already bubble + are composed.)

## `showcase.latte` Shape

Take the current content of `packages/frontend-demo/resources/views/counter.latte` between the opening `<section class="demo-primitives">` and the closing `</section>` of the form-controls block (lines 3-264 inclusive), and copy it verbatim into `packages/theme-blank-demo/resources/views/showcase.latte`. DO NOT carry over the `<markommerce-counter>` line at the top — that stays in frontend-demo (task 021 keeps it there).

Crucial sub-pieces preserved verbatim:
- Both `<section>` wrappers (`demo-primitives`, `demo-form-controls`)
- The `{syntax off}…{/syntax}` block around the inline mk-form event-listener script (note: the old script targets the first `mk-form` on the page via `document.querySelector('mk-form')?.addEventListener(...)` — keep this exactly as-is)
- All `mk-stack`, `mk-cluster`, `mk-grid`, `mk-container`, `mk-sidebar`, `mk-switcher`, `mk-cover`, `mk-divider`, `mk-heading`, `mk-text`, `mk-link`, `mk-badge`, `mk-button`, `mk-input`, `mk-textarea`, `mk-select`, `mk-checkbox`, `mk-radio`, `mk-switch`, `mk-field`, `mk-fieldset`, `mk-form` examples

## Requirements (Test Descriptions)

Tests go in `packages/theme-blank-demo/tests/Feature/ThemeBlankDemoControllerTest.php` (extend the file created by task 018). These are HTTP-level smoke tests using the same `demoTestBuildRouter` pattern as frontend-demo.

You will need three helpers in this test file, mirroring `frontend-demo/tests/Feature/DemoControllerTest.php` lines 44-164:

- `themeBlankDemoTestCleanup(string $dir)` — verbatim copy of `demoTestCleanup` (helper has no module-specific content).
- `themeBlankDemoTestEnsureManifest(string $basePath)` — must write a manifest entry keyed by the NEW Vite entry path `packages/theme-blank-demo/resources/js/main.ts`, not the frontend-demo one.
- `themeBlankDemoTestBuildRouter(...)` — same shape as `demoTestBuildRouter` but: (1) registers `markommerce/theme-blank-demo` instead of `markommerce/frontend-demo` in the `ModuleRepository`; (2) discovers from `ThemeBlankDemoController::class`; (3) binds `ThemeBlankDemoConfig` + `EnsureThemeBlankDemoEnabledMiddleware` + `ThemeBlankDemoController` into the container; (4) does NOT register `markommerce/frontend-demo` (otherwise `DemoCounterComponent` would be auto-discovered by `DiscoveringComponentCollector` and pollute the showcase layout).

Each test must also set `vite.entry` in its `ConfigRepository` to `packages/theme-blank-demo/resources/js/main.ts` (NOT the frontend-demo entry).

- [ ] `it returns 200 OK when theme_blank_demo.enabled is true and GET /markommerce/_demo/theme-blank is requested`
- [ ] `it returns 404 when theme_blank_demo.enabled is false because EnsureThemeBlankDemoEnabledMiddleware short-circuits before LayoutMiddleware runs`
- [ ] `it includes the marko/vite generated script tag referencing packages/theme-blank-demo/resources/js/main.ts in the response head`
- [ ] `it the rendered page contains a Layout primitives heading`
- [ ] `it the rendered page contains a Typography primitives heading`
- [ ] `it the rendered page contains a Form Controls heading`
- [ ] `it the layout/base.latte file loads the Vite entry for packages/theme-blank-demo/resources/js/main.ts (file-content assertion)`
- [ ] `it the showcase.latte file contains the {syntax off} guard around the inline mk-form event-listener script (file-content assertion)`

Note: full per-element assertions (`<mk-stack`, `<mk-button`, etc.) are migrated in task 021 to keep this task focused.

## Acceptance Criteria
- All requirements have passing tests
- The `showcase.latte` body is byte-identical to the corresponding slice of `frontend-demo/counter.latte` (only the file location changes — content is moved as-is in task 021; task 019 just creates the new copy)
- The `base.latte` template uses `theme-blank-demo` view namespace correctly (e.g. `{vite('packages/theme-blank-demo/resources/js/main.ts')}`)
- `composer test` passes
- No regressions in `frontend-demo` tests (those don't change here — they're modified in task 021)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
