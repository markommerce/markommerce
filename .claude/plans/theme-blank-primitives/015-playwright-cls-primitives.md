# Task 015: Playwright CLS Spec for All 12 Primitives

**Status**: completed
**Depends on**: 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014
**Retry count**: 0

## Description
Add a new Playwright fixture and spec that renders all 12 Phase 2 primitives with realistic content and asserts the page produces zero cumulative layout shift through load + idle. This task does NOT modify the existing `cls.spec.ts` from Phase 1 — that test continues to run alongside the new spec. The new fixture inlines every component CSS file (with cascade layers and `@container` queries preserved) into a `<style>` block, then renders one sample of each primitive.

## Context

- Files to create:
  - `packages/theme-blank/tests/Browser/fixtures/primitives-page.html` — the new fixture
  - `packages/theme-blank/tests/Browser/primitives-cls.spec.ts` — the new Playwright spec

- Reference patterns (Phase 1):
  - `packages/theme-blank/tests/Browser/fixtures/base-page.html` — shows the inlined-CSS strategy
  - `packages/theme-blank/tests/Browser/cls.spec.ts` — shows the `PerformanceObserver` + `setContent` pattern

### Fixture HTML strategy

The fixture inlines (in order):
1. Tokens CSS (already inlined in `base-page.html` — copy verbatim)
2. Base CSS including the extended `:not(:defined)` safety net from task 002
3. Layouts CSS (already inlined in `base-page.html`)
4. **All 12 component CSS files**, with `@container` queries preserved as-is and any `--mk-*` references intact

The fixture body renders ALL 12 primitives with realistic content. Example structure:

```html
<body>
  <main class="mk-layout-1col">
    <mk-stack gap="4">
      <mk-heading level="1" size="2xl">Primitives showcase (single-tag form)</mk-heading>
      <mk-heading size="2xl"><h1>Primitives showcase (two-tag form)</h1></mk-heading>
      <mk-text variant="lead">One of each primitive, rendered here for CLS verification.</mk-text>
      <mk-text variant="lead"><p>Same content in two-tag form for parity verification.</p></mk-text>

      <mk-cluster gap="2">
        <mk-badge variant="primary">Primary</mk-badge>
        <mk-badge variant="success">Success</mk-badge>
        <mk-badge variant="warning">Warning</mk-badge>
        <mk-badge variant="danger">Danger</mk-badge>
      </mk-cluster>

      <mk-divider></mk-divider>

      <mk-grid min="12rem" gap="3">
        <div>Grid cell 1</div>
        <div>Grid cell 2</div>
        <div>Grid cell 3</div>
      </mk-grid>

      <mk-sidebar width="14rem">
        <aside>Sidebar</aside>
        <section>Main content</section>
      </mk-sidebar>

      <mk-switcher threshold="30rem" gap="3">
        <div>Switcher A</div>
        <div>Switcher B</div>
        <div>Switcher C</div>
      </mk-switcher>

      <mk-container size="md">
        <mk-text>Constrained content within a container.</mk-text>
      </mk-container>

      <mk-cover min-height="40vh">
        <header class="mk-cover-header"><mk-heading level="2" size="lg">Cover header</mk-heading></header>
        <section><mk-text>Centered main</mk-text></section>
        <footer class="mk-cover-footer"><mk-text variant="small">Cover footer</mk-text></footer>
      </mk-cover>

      <mk-link variant="default" underline="hover">
        <a href="#">Example link</a>
      </mk-link>
    </mk-stack>
  </main>
</body>
```

The fixture does NOT load JS — components are NOT upgraded. This is intentional: the test verifies that the **unupgraded** (`:not(:defined)`) state has the same layout as the upgraded state, which is the CLS invariant. The `:not(:defined)` safety-net rule from task 002 is what makes this possible.

A separate test variant (within the same spec file) loads the same fixture but injects a script that defines all components and verifies CLS remains 0 across the upgrade.

**The stub classes used in the upgrade test MUST also exercise the Lit-style light-DOM render-root pattern**, otherwise the test only proves the trivial case (`class extends HTMLElement {}` does no DOM work at upgrade). Specifically, the stub should:
- Override `connectedCallback()` to read the relevant length attribute (where applicable — `min`, `width`, `min-height`, `threshold`) and set the corresponding `--mk-*` inline style synchronously, mirroring what the real Phase 2 component does.
- For elements that would normally use Lit's render(): insert a synthetic `<!---->` Comment node as the first child of the host during `connectedCallback` to mirror Lit's ChildPart marker. This proves that the Comment-marker insertion does not itself cause CLS.

If even this synthetic upgrade keeps CLS at 0, we have strong confidence the real components will too. The trade-off is that we are NOT loading the actual Lit-built bundle into Playwright (that would require a fully-built Vite output + a dev server, which is out of scope for the CLS smoke test). The synthetic upgrade is a deliberate approximation; consumers who want full end-to-end coverage should run their own integration tests against a real build.

### Spec structure

```typescript
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const fixtureHtml = readFileSync(
  resolve(__dirname, 'fixtures/primitives-page.html'),
  'utf-8',
);

test.describe('primitives CLS', () => {
  test('all 12 primitives produce zero CLS in unupgraded state', async ({ page }) => {
    await page.addInitScript(() => {
      (window as any).__clsEntries = [];
      const observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!(entry as any).hadRecentInput) {
            (window as any).__clsEntries.push((entry as any).value);
          }
        }
      });
      observer.observe({ type: 'layout-shift', buffered: true });
    });
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    const cls = await page.evaluate(() => {
      const entries: number[] = (window as any).__clsEntries ?? [];
      return entries.reduce((acc, v) => acc + v, 0);
    });
    expect(cls).toBe(0);
  });

  test('all 12 primitives produce zero CLS through upgrade', async ({ page }) => {
    // Same fixture, but inject customElements.define() for each tag mid-page.
    await page.addInitScript(() => {
      (window as any).__clsEntries = [];
      const observer = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!(entry as any).hadRecentInput) {
            (window as any).__clsEntries.push((entry as any).value);
          }
        }
      });
      observer.observe({ type: 'layout-shift', buffered: true });
    });
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });

    // Define all 12 tags inside the page; CSS does the styling so the visual result is unchanged.
    // The stub classes synchronously sync length attributes to inline CSS variables (mirroring
    // the real components) AND insert a Lit-style Comment marker as the first child to prove
    // that marker insertion does not cause CLS.
    await page.evaluate(() => {
      const lengthAttrMap: Record<string, { attr: string; varName: string }> = {
        'mk-grid': { attr: 'min', varName: '--mk-grid-min' },
        'mk-sidebar': { attr: 'width', varName: '--mk-sidebar-width' },
        'mk-switcher': { attr: 'threshold', varName: '--mk-switcher-threshold' },
        'mk-cover': { attr: 'min-height', varName: '--mk-cover-min-height' },
      };
      const tags = [
        'mk-stack', 'mk-cluster', 'mk-grid', 'mk-container',
        'mk-sidebar', 'mk-switcher', 'mk-cover', 'mk-divider',
        'mk-heading', 'mk-text', 'mk-link', 'mk-badge',
      ];
      for (const tag of tags) {
        if (customElements.get(tag)) continue;
        const sync = lengthAttrMap[tag];
        customElements.define(tag, class extends HTMLElement {
          connectedCallback() {
            if (sync) {
              const value = this.getAttribute(sync.attr);
              if (value !== null && value !== '') {
                this.style.setProperty(sync.varName, value);
              }
            }
            // Mirror Lit's ChildPart marker so the test also covers marker insertion.
            this.insertBefore(document.createComment(''), this.firstChild);
          }
        });
      }
    });
    await page.waitForTimeout(500);
    const cls = await page.evaluate(() => {
      const entries: number[] = (window as any).__clsEntries ?? [];
      return entries.reduce((acc, v) => acc + v, 0);
    });
    expect(cls).toBe(0);
  });
});
```

The two-test split proves both invariants: (a) the unupgraded element is laid out correctly via the safety net, and (b) the upgrade itself causes no shift.

## Requirements (Test Descriptions)
- [ ] `it ships packages/theme-blank/tests/Browser/fixtures/primitives-page.html with inlined CSS for tokens, base, layouts, and all 12 component CSS files`
- [ ] `the fixture renders one sample of each of the 12 Phase 2 primitives`
- [ ] `the fixture <style> block includes the :not(:defined) selector group from base.css (covering all 12 tag names, even though its declaration block is empty by design — see task 002)`
- [ ] `it ships packages/theme-blank/tests/Browser/primitives-cls.spec.ts with two tests: unupgraded CLS and post-upgrade CLS`
- [ ] `the post-upgrade test's stub classes (a) sync length attributes to inline CSS variables in connectedCallback and (b) insert a Comment marker as the first child during connectedCallback, mirroring the real Phase 2 components`
- [ ] `running npm run test:cls executes the new spec and both tests pass with CLS === 0`
- [ ] `the existing Phase 1 cls.spec.ts continues to pass after this task`
- [ ] `the README at packages/theme-blank/tests/Browser/README.md is updated to document the new fixture and the rationale for splitting "unupgraded" vs "upgraded" CLS tests`

## Acceptance Criteria
- All requirements have passing tests
- Both new Playwright tests run in under 10 seconds combined
- The unupgraded CLS test FAILS if a component's `:not(:defined)` style differs significantly from its upgraded style (this is the regression-prevention mechanism for Phase 3+)
- No regressions in `composer test`, `npm test`, or the existing `npm run test:cls` baseline

## Implementation Notes
(Left blank — filled in by programmer during implementation)
