# Task 016: Frontend-Demo Primitives Section

**Status**: completed
**Depends on**: 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014
**Retry count**: 0

## Description
Extend the existing `/markommerce/_demo` route in `frontend-demo` to render a "Primitives" section that visually showcases every Phase 2 primitive. The section is appended below the existing counter demo. No new route — the existing controller and Latte view are updated in place.

## Context

- Files to update:
  - `packages/frontend-demo/resources/views/counter.latte` — append the Primitives section. This template is the body of `DemoCounterComponent` (`#[Component(template: 'frontend-demo::counter', slot: 'content')]`); it is rendered into the `content` slot of `DemoLayout` (`#[Component(template: 'frontend-demo::layout/base', slots: ['content'])]`). Appending HTML to `counter.latte` directly extends the content of the single component that slots into the layout — no new component registration is required. The component name will become slightly misleading (it renders both the counter and primitives) but the path of least resistance is documented as acceptable in the plan.
  - `packages/frontend-demo/resources/js/main.ts` — verify nothing breaks. The Vite scanner already discovers theme-blank's `markommerce.extension` and the Phase 2 task 002 update wires `./components` into the theme-blank index. No edits expected here.

- Files to verify/create:
  - `packages/frontend-demo/composer.json` — already requires `markommerce/theme-blank` from Phase 1, no change
  - `packages/frontend-demo/package.json` — Phase 1 ships with only `@markommerce/frontend` as a direct dep. **No change is required** — the Vite scanner (`build/vite-plugin-markommerce.ts`) walks every `packages/*/package.json` looking for a `markommerce.extension` block; it does not require dependency declarations between packages. Adding `@markommerce/theme-blank` as an explicit dep would be a defensible cleanup but is NOT necessary for the scanner to pick up the components entry.

- Reference: the existing demo route is registered via `DemoController::index()` with `#[Get('/markommerce/_demo')]`. The view is selected by Marko's auto-template resolution from the action method name; `index` → `counter.latte` per the current layout/template config (verify in `src/Layout/DemoLayout.php`).

### Section structure

The Primitives section is a single `<section class="demo-primitives">` block that renders one h3 per category (Layout, Typography) and, within each, demonstrations of each primitive with code labels:

```html
<section class="demo-primitives">
  <mk-heading size="2xl"><h2>Layout primitives</h2></mk-heading>
  <mk-stack gap="6">
    <article>
      <mk-heading level="3" size="lg">mk-stack</mk-heading>
      <mk-stack gap="3">
        <div>First item</div>
        <div>Second item</div>
        <div>Third item</div>
      </mk-stack>
    </article>
    <article>
      <mk-heading level="3" size="lg">mk-cluster</mk-heading>
      <mk-cluster gap="2">
        <button>Action A</button>
        <button>Action B</button>
        <button>Action C</button>
      </mk-cluster>
    </article>
    <!-- mk-grid, mk-container, mk-sidebar, mk-switcher, mk-cover, mk-divider -->
  </mk-stack>

  <mk-heading level="2" size="2xl">Typography primitives</mk-heading>
  <mk-stack gap="4">
    <!--
      Examples to include:
      - mk-heading single-tag: <mk-heading level="2" size="xl">Heading text</mk-heading>
      - mk-heading two-tag (legacy/SEO):
          <mk-heading size="xl"><h2>SEO-critical heading</h2></mk-heading>
      - mk-text single-tag: <mk-text variant="lead">Lead paragraph text</mk-text>
      - mk-text two-tag: <mk-text variant="body"><p>Body paragraph with <em>nested</em> elements</p></mk-text>
      - mk-link (wrapper, always two-tag): <mk-link variant="muted"><a href="#">Link text</a></mk-link>
      - mk-badge (always single-tag): <mk-badge variant="primary">Badge</mk-badge>
    -->
  </mk-stack>
</section>
```

### `main.ts` wiring

The existing `frontend-demo/resources/js/main.ts` imports `@markommerce/theme-blank/css/*` plus `./.generated/extensions` and calls `defineAllComponents()`. As long as `@markommerce/theme-blank` is a dependency in `frontend-demo/package.json`, the Vite scanner picks up its `markommerce.extension` entry (which Phase 2 wires to include `./components`). **No edits to `main.ts` are required** if the scanner picks up the package correctly. If it does not, add an explicit `import '@markommerce/theme-blank';` near the top of `main.ts`.

### Demo controller test

`packages/frontend-demo/tests/Feature/DemoControllerTest.php` already contains 13 `it(...)` cases and Phase 1 added one that asserts ordering of `layers.css` → `tokens.css` → `counter.css` in `main.ts`. Phase 2 additions MUST:
- Append new `it(...)` cases to the END of the file — do not modify the existing helper functions (`demoTestCleanup`, `demoTestBuildRouter`, `demoTestEnsureManifest`, `themeBlankTestBuildConfig`).
- Reuse the existing helpers verbatim — every new test that needs a rendered response calls `demoTestBuildRouter()` and `demoTestEnsureManifest()` exactly like the `'it embeds the <markommerce-counter> element...'` case (lines 266-304 of the current file).
- Each new `it(...)` case generates its own unique cache directory via `sys_get_temp_dir() . '/latte-demo-primitives-{n}-' . bin2hex(random_bytes(8))` and cleans up with `demoTestCleanup()`.
- The existing layer-order test (`'it loads the @markommerce/frontend cascade layers and @markommerce/theme-blank tokens CSS...'`, lines 376-389) MUST still pass. If task 016's `main.ts` changes break ordering, fix the order rather than weakening the assertion.
- Use the same Pest `it(...)` style as the rest of the file. Do not introduce `describe()` blocks.

The plan's per-component tag-presence checks (one `it(...)` per tag) can be collapsed into a single test that iterates over all 12 tag names and asserts each appears in the body, to avoid 12 separate router-spin-up costs. Pattern:

```php
it('the rendered page contains every Phase 2 primitive tag', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-demo-primitives-tags-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);
    // ...standard router setup using demoTestBuildRouter / demoTestEnsureManifest...
    $body = $response->body();
    foreach (['mk-stack', 'mk-cluster', 'mk-grid', 'mk-container', 'mk-sidebar',
              'mk-switcher', 'mk-cover', 'mk-divider', 'mk-heading', 'mk-text',
              'mk-link', 'mk-badge'] as $tag) {
        expect($body)->toContain('<' . $tag);
    }
    // ...cleanup...
});
```

## Requirements (Test Descriptions)
- [ ] `the /markommerce/_demo route renders a Layout Primitives heading`
- [ ] `the /markommerce/_demo route renders a Typography Primitives heading`
- [ ] `the rendered page contains at least one mk-stack element`
- [ ] `the rendered page contains at least one mk-cluster element`
- [ ] `the rendered page contains at least one mk-grid element`
- [ ] `the rendered page contains at least one mk-container element`
- [ ] `the rendered page contains at least one mk-sidebar element`
- [ ] `the rendered page contains at least one mk-switcher element`
- [ ] `the rendered page contains at least one mk-cover element`
- [ ] `the rendered page contains at least one mk-divider element`
- [ ] `the rendered page contains at least one mk-heading element`
- [ ] `the rendered page contains at least one mk-text element`
- [ ] `the rendered page contains at least one mk-link element`
- [ ] `the rendered page contains at least one mk-badge element`
- [ ] `the original counter demo continues to render on the same page`
- [ ] `the rendered HTML imports the @markommerce/theme-blank components entry (via the .generated/extensions sentinel or an explicit import)`

## Acceptance Criteria
- All requirements have passing tests
- The route renders cleanly in a real browser (manual visual check during implementation)
- No new routes, controllers, or Marko Layout classes are introduced — the existing `DemoController::index()` and `DemoLayout` handle everything
- Existing `DemoControllerTest` assertions continue to pass
- The Vite dev server (`npm run dev`) builds without errors when the new Latte content references the primitives

## Implementation Notes
(Left blank — filled in by programmer during implementation)
