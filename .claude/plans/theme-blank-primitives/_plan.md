# Plan: theme-blank Primitives (Phase 2)

## Created
2026-05-17

## Status
completed

## Objective
Ship 12 unstyled, CSS-driven layout and typography primitives for `@markommerce/theme-blank` — `mk-stack`, `mk-cluster`, `mk-grid`, `mk-container`, `mk-sidebar`, `mk-switcher`, `mk-cover`, `mk-divider`, `mk-heading`, `mk-text`, `mk-link`, `mk-badge` — with zero CLS, light-DOM Lit base class, per-component docs, and an extended Playwright CLS smoke test that verifies all 12 produce CLS === 0.

## Related Issues
none

## Discovery Notes

**Phase 1 deliverables already on `feature/theme-blank-foundation`** (this plan branches from there, not from `main`):
- `@markommerce/theme-blank` package with `--mk-*` tokens, `base.css`, `layouts.css`, 6 Latte page layouts, stub `showToast`/`openModal`
- Playwright CLS smoke test (`packages/theme-blank/tests/Browser/cls.spec.ts`) — inlines CSS into a fixture HTML and asserts CLS === 0 against `1column.latte`-shaped DOM
- Mixin registry in `@markommerce/frontend` (`registerBase`, `addMixin`, `defineAllComponents`)
- `MarkommerceCounterElement` in `frontend-demo` proves the light-DOM Lit pattern via `createRenderRoot() { return this; }`

**Architectural decisions resolved during clarification:**

1. **Shared `MkElement` base class** — lives in `@markommerce/frontend`, extends `LitElement`, overrides `createRenderRoot()` to return `this` (light DOM). Every Phase 2-5 component extends `MkElement`. This is the single source of truth for "what a Markommerce custom element looks like."
2. **CSS-only primitives** — for layout + typography, the JS class is *ceremonial*: it just registers the tag via `customElements.define()` (through the mixin registry) so `:not(:defined)` CSS works and future mixins can decorate it. The component renders no markup; `render()` returns the existing light-DOM children unchanged. **All visual behavior is in `@layer components` CSS**, with `@container` queries for responsive primitives (`mk-sidebar`, `mk-switcher`).
3. **No Latte partials in Phase 2** — primitives are used as raw HTML tags (`<mk-stack gap="4">…</mk-stack>`).
4. **Demo surface** — extend the existing `/markommerce/_demo` route with a "Primitives" section grouped by category.
5. **Typography DX refinement (added post-devil's-advocate)** — `mk-heading` and `mk-text` accept text content directly as a single tag (e.g. `<mk-heading level="2" size="lg">About</mk-heading>`); `mk-heading` synchronously injects `role="heading"` + `aria-level` in `connectedCallback` for AT/SEO. Both also support the legacy two-tag form (`<mk-heading><h2>X</h2></mk-heading>`) via `:has()`-gated CSS that defers styling to the inner element — useful for SEO-critical content where native h-tags are preferred. `mk-link` stays as a wrapper around `<a>` because the native anchor cannot be replaced without losing right-click / middle-click / "copy link" UX. `mk-badge` is single-tag (no inner element required).

**Important Vite/test wiring details discovered in Phase 1:**
- `frontend-demo/resources/js/main.ts` already orchestrates CSS-layer order (`layers.css` → `open-props` → `tokens.css` → `base.css` → `layouts.css` → `extensions` → component CSS → `defineAllComponents()`). New primitive CSS imports must slot in via the `@markommerce/theme-blank` package's side-effect entry, not by hand-editing `main.ts`.
- Vitest happy-dom does not support `customElements` polyfills for `@container` queries — `@container` query *resolution* is not tested in unit tests; we rely on the Playwright CLS suite + visual demo verification.
- The Playwright fixture HTML inlines CSS literally with `@custom-media` resolved (e.g., `@media (min-width: 768px)`). The new fixture rule: add the new component's CSS to the inlined `<style>` block in the same resolved form.

**Discovery gotcha — counter demo does NOT match the architectural rule:**
The `MarkommerceCounterElement` Lit class uses `render()` to *produce* `<span>` and `<button>` children at upgrade time. This is fine for the counter (no SEO need, no FOUCE risk because the element is fully invisible until JS arrives), but it is **not** the Phase 2 pattern. Phase 2 components render canonical HTML from the server *into the slot of the custom element*. The `MkElement` base must NOT default to an active `render()` — its default `render()` returns `nothing` (Lit's no-op token), and downstream subclasses opt in to active rendering. This is the central CLS-prevention contract.

## Scope

### In Scope

- **`MkElement` base class** in `@markommerce/frontend` (extends `LitElement`, light DOM, no-op default `render()`, exported from package entry).
- **12 primitive Lit element classes** in `packages/theme-blank/resources/js/components/`, one file per component, all extending `MkElement`.
- **12 component CSS files** in `packages/theme-blank/resources/css/components/`, all inside `@layer components`, referencing only `--mk-*` tokens and standard CSS (including `@container` queries where needed).
- **Single side-effect entry** in `packages/theme-blank/resources/js/components/index.ts` that imports each component module and calls `registerBase()` for it (per the existing registry contract). Wired into the package's `markommerce.extension` entry so the Vite scanner picks it up.
- **`:not(:defined)` safety-net rule** in `theme-blank/resources/css/base.css` covering all 12 new tag names.
- **Per-component Vitest unit tests** (one `.test.ts` per component) covering: tag registration, attribute reflection where applicable, light-DOM preservation (children unchanged after upgrade).
- **Playwright CLS fixture extension** — `packages/theme-blank/tests/Browser/fixtures/primitives-page.html` (a new fixture, NOT a modification of `base-page.html`) renders all 12 primitives with realistic content, with all component CSS inlined. New `primitives-cls.spec.ts` asserts CLS === 0.
- **12 per-component docs pages** at `docs/src/content/docs/packages/theme-blank/mk-{name}.md`, each following the per-component template (one-liner, HTML usage, attributes table, slots, events, CSS custom properties, variants/states, extending example, accessibility notes).
- **Updated docs index page** (`docs/src/content/docs/packages/theme-blank/index.md`) with a new `## Components` section linking to all 12 pages.
- **Frontend-demo update** — extend `/markommerce/_demo` Latte view with a "Primitives" section grouped by category (Layout, Typography), one visual sample per component.

### Out of Scope

- Forms components (Phase 3): `mk-button`, `mk-input`, `mk-textarea`, `mk-select`, `mk-checkbox`, `mk-radio`, `mk-switch`, `mk-field`, `mk-fieldset`, `mk-quantity-input`.
- Feedback components (Phase 4): `mk-alert`, `mk-toast`, `mk-spinner`, `mk-skeleton`, `mk-modal`, `mk-drawer`, plus the *real* `showToast`/`openModal` implementations.
- Navigation + money (Phase 5): `mk-breadcrumbs`, `mk-menu`, `mk-tabs`, `mk-pagination`, `mk-price`.
- Icons (deferred indefinitely).
- Latte partials for primitives.
- A new `/markommerce/_demo/primitives` route (the existing route gets extended in place).
- Shadow-DOM components, Astro/Starlight sidebar config edits (Starlight not installed yet).

## Success Criteria

- [ ] All 12 primitive custom elements register, are styled via CSS-only, and produce zero CLS in the Playwright suite
- [ ] `MkElement` base class exported from `@markommerce/frontend` with light-DOM default and no-op default `render()`
- [ ] `:not(:defined)` safety-net rule covers all 12 tags in `theme-blank/base.css`
- [ ] Every primitive has a Vitest unit test asserting registration + light-DOM preservation
- [ ] Every primitive has a docs page following the per-component template; docs index links to each
- [ ] `/markommerce/_demo` route renders a "Primitives" section showcasing all 12 components
- [ ] `composer test` passes (PHP suite)
- [ ] `npm test` passes (Vitest suite) — no regression in coverage
- [ ] `npm run test:cls` passes (Playwright — original `cls.spec.ts` AND new `primitives-cls.spec.ts` both green)
- [ ] `./vendor/bin/phpcs` passes
- [ ] `./vendor/bin/phpstan analyse` passes
- [ ] Stylelint passes on all new CSS
- [ ] Visual smoke test of `/markommerce/_demo` confirms every primitive renders correctly without JS (verifiable by disabling JS in DevTools)

## Task Overview

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Create `MkElement` base in `@markommerce/frontend` | - | completed |
| 002 | Theme-blank components entry + extend `:not(:defined)` safety net | 001 | completed |
| 003 | `mk-stack` — vertical rhythm primitive | 002 | completed |
| 004 | `mk-cluster` — horizontal flex-wrap row | 002 | completed |
| 005 | `mk-grid` — auto-fit responsive grid | 002 | completed |
| 006 | `mk-container` — max-width content container | 002 | completed |
| 007 | `mk-sidebar` — sidebar + main with implicit-flexbox collapse | 002 | completed |
| 008 | `mk-switcher` — row→column switch via flex-basis arithmetic | 002 | completed |
| 009 | `mk-cover` — header/main/footer full-height frame | 002 | completed |
| 010 | `mk-divider` — styled separator | 002 | completed |
| 011 | `mk-heading` — decoupled semantic/visual heading | 002 | completed |
| 012 | `mk-text` — body/lead/small/muted variants | 002 | completed |
| 013 | `mk-link` — variant + underline-policy link | 002 | completed |
| 014 | `mk-badge` — neutral/primary/success/warning/danger badge | 002 | completed |
| 015 | Playwright CLS fixture + spec covering all 12 primitives | 003-014 | completed |
| 016 | Extend `frontend-demo` route with "Primitives" section | 003-014 | completed |
| 017 | Docs index update + cross-link wiring | 003-014 | completed |

## Architecture Notes

### Hard CLS-prevention rule (reaffirmed from Phase 1)

> **Every component must render correctly without JavaScript.** Custom-element JS is a behavior layer on top of canonical server-rendered HTML, never a replacement for it.

For Phase 2 primitives this means:
- The component's `render()` returns Lit's `nothing` (or simply does not override). No light-DOM children are created by the JS upgrade.
- All visual styling is in `@layer components` CSS, keyed on the custom-element selector (e.g., `mk-stack[gap="4"] { gap: var(--mk-space-4); }`).
- Attributes are CSS-driven via `[attribute="value"]` selectors — *not* via `@property` decorators that re-render templates.
- The actual CLS-prevention mechanism is the `@layer components` tag-selector rules (e.g. `mk-stack { display: flex; ... }`). CSS matches on tag name regardless of custom-element registration, so layout is correct from first paint. The `:not(:defined)` selector group in `@layer base` is retained as a documentation hook and downstream override point (see task 002). It is intentionally NOT `display: revert` — that would resolve to the UA default `display: inline`, which is wrong for flex/grid/block primitives. Layer order (`components` > `base`) ensures the components-layer rules always win regardless of selector specificity.
- Responsive behavior uses `@container` queries, not JavaScript ResizeObserver.

### `MkElement` contract

```typescript
import { LitElement, nothing } from 'lit';

export class MkElement extends LitElement {
  override createRenderRoot(): HTMLElement {
    return this;
  }

  // Must match LitElement.render(): unknown — do NOT narrow to typeof nothing,
  // or TypeScript will reject subclasses that override with TemplateResult.
  override render(): unknown {
    return nothing;
  }
}
```

Returning `nothing` from `render()` does NOT replace the existing light-DOM children. Lit-element's `createRenderRoot()` sets `renderOptions.renderBefore = renderRoot.firstChild`; the lit-html ChildPart is therefore inserted as a Comment marker *before* the first existing element child, and its content range is initially empty. `nothing` keeps that range empty, leaving every server-rendered child intact. Subclasses that want active rendering (Phase 3+ forms, Phase 4+ overlays) opt in by overriding `render()` to return a `TemplateResult` — the template will be inserted between the Comment marker and the first pre-existing child, NOT replace the children.

### Attribute model

Each primitive accepts a small set of declarative attributes. Attributes are reflected only when needed for CSS selectors. The standard pattern:

```typescript
import { property } from 'lit/decorators.js';

export class MkStackElement extends MkElement {
  @property({ type: String, reflect: true }) gap?: string;
  @property({ type: String, reflect: true }) align?: 'start' | 'center' | 'end' | 'stretch';
}
```

CSS targets the reflected attribute:

```css
@layer components {
  mk-stack { display: flex; flex-direction: column; }
  mk-stack[gap="1"] { gap: var(--mk-space-1); }
  mk-stack[gap="2"] { gap: var(--mk-space-2); }
  /* ... */
}
```

Sensible defaults are encoded in CSS rather than JS — `mk-stack { display: flex; gap: var(--mk-space-3); }` so an attribute-less `<mk-stack>` already has a usable default.

### CSS structure

```
packages/theme-blank/resources/css/
  tokens.css             # @layer tokens (Phase 1)
  base.css               # @layer base + :not(:defined) safety net (extended Phase 2)
  layouts.css            # @layer theme (Phase 1)
  components/
    mk-stack.css         # @layer components
    mk-cluster.css       # @layer components
    mk-grid.css          # @layer components
    mk-container.css     # @layer components
    mk-sidebar.css       # @layer components, uses @container
    mk-switcher.css      # @layer components, uses @container
    mk-cover.css         # @layer components
    mk-divider.css       # @layer components
    mk-heading.css       # @layer components
    mk-text.css          # @layer components
    mk-link.css          # @layer components
    mk-badge.css         # @layer components
```

The component CSS files are imported by their matching `.ts` modules via `import './mk-stack.css';`-style side-effect imports. Vite resolves these at build time and includes them in the bundle in the correct order via cascade-layer wiring.

### JS structure

```
packages/theme-blank/resources/js/
  index.ts                              # public exports (showToast, openModal stubs from Phase 1)
  components/
    index.ts                            # side-effect file: imports + registers all 12 primitives
    mk-stack.ts                         # MkStackElement extends MkElement
    mk-cluster.ts
    mk-grid.ts
    mk-container.ts
    mk-sidebar.ts
    mk-switcher.ts
    mk-cover.ts
    mk-divider.ts
    mk-heading.ts
    mk-text.ts
    mk-link.ts
    mk-badge.ts
```

The package's `markommerce.extension` entry in `package.json` already points to `./resources/js/index.ts` (set in Phase 1). For Phase 2 it must be updated to also import `./components/index.ts` for its side effects, so the Vite scanner picks up the registrations.

### Container queries — NOT used by mk-sidebar or mk-switcher

The plan originally proposed `@container` queries for `mk-sidebar` and `mk-switcher`, but those components ship with the Every Layout *implicit* responsive patterns instead:

- `mk-sidebar` uses pure flexbox with `flex-basis` + `min-inline-size: 50%` — wraps to a column when the main pane can't fit half the parent width (task 007).
- `mk-switcher` uses `calc((threshold - 100%) * 999)` flex-basis arithmetic with `:nth-last-child(n + 5)` limit enforcement (task 008).

This avoids the (still-spotty) browser support for `@container style(...)` queries on arbitrary custom properties and gives the same UX without any JS measurement. The components do NOT declare `container-type: inline-size` and DO NOT use `@container` at-rules.

If a Phase 3+ primitive needs a true `@container` query (e.g. `mk-card`), that primitive will introduce its own container scope. The stylelint config must accept `@container` — task 002 verifies and adds it to `ignoreAtRules` if needed.

### Vitest unit test pattern

Each component test asserts:
1. The tag is registered after the component module is imported (via `getRegisteredComponents()` from `@markommerce/frontend`).
2. After `defineAllComponents()`, the element upgrades without removing its light-DOM children (CLS invariant).
3. Reflected attributes appear on the DOM (`element.getAttribute('gap')` matches `element.gap`).

Tests use happy-dom (already configured in `vite.config.ts`). They do NOT assert on `@container` behavior (happy-dom does not support container queries; that's the Playwright suite's job).

### Playwright CLS strategy

A **new** fixture `packages/theme-blank/tests/Browser/fixtures/primitives-page.html` is added — it does NOT replace `base-page.html`. The fixture inlines all CSS (tokens, base, layouts, every component .css) with `@custom-media` and `@container` queries resolved literally. The HTML body renders one of each primitive with realistic content (e.g., `<mk-stack><h2>Title</h2><p>Body</p></mk-stack>`). The new spec `primitives-cls.spec.ts` mirrors the existing CLS observer pattern from `cls.spec.ts` and asserts CLS === 0.

### Per-component docs page template

Every page lives at `docs/src/content/docs/packages/theme-blank/mk-{name}.md` with frontmatter:

```yaml
---
title: mk-{name}
description: One-line description of the primitive.
---
```

Required sections (in order):
1. **Intro paragraph** — one sentence on what it does, one on when to use it. No `## Overview` heading.
2. **HTML Usage** — minimal HTML example.
3. **Attributes** — Markdown table with columns: Name / Type / Default / Description. Skip if no attributes.
4. **Slots** — for primitives this is always "default slot accepts any HTML." Document explicitly anyway.
5. **Events** — "This component emits no events." (most primitives) or table of event names + payloads.
6. **CSS Custom Properties** — list of `--mk-*` tokens this component reads, plus any `mk-{name}-*` tokens it exposes for fine-grained overrides. Skip if none.
7. **Variants & States** — what each attribute combination produces visually.
8. **Extending** — a short example showing how a downstream package would override the CSS or add a mixin via `addMixin('mk-stack', myMixin, …)`.
9. **Accessibility** — keyboard/ARIA notes. For most primitives this is "this is a presentational wrapper; the slotted content is responsible for semantics."

The docs index page (`packages/theme-blank/index.md`) gains a new `## Components` section listing all 12 with one-line descriptions and links.

## Risks & Mitigations

- **Risk:** A component's CSS-only default produces non-zero CLS during initial paint (e.g., `display: block` → `display: flex` transition).
  - **Mitigation:** The `:not(:defined)` safety-net rule applies the *final* layout values to the unupgraded element. Phase 1 contract: pre-upgrade and post-upgrade layouts must be identical. Playwright spec catches regressions.

- **Risk:** `@container` query support assumed for `mk-sidebar` / `mk-switcher`. If a downstream consumer targets older browsers, those primitives silently fail to collapse.
  - **Mitigation:** Document the browser-support floor (Chromium 105+, Firefox 110+, Safari 16+) on the relevant component docs pages and on the docs index. The components still render — they just don't collapse.

- **Risk:** Adding 12 component CSS imports to the JS bundle bloats the demo build.
  - **Mitigation:** Vite tree-shakes side-effect imports correctly because CSS imports are marked side-effectful. Acceptance check: bundle size of `frontend-demo` does not grow by more than ~5 KB minified after this plan.

- **Risk:** `MkElement` placed in `@markommerce/frontend` couples the kernel to Lit. The kernel currently exports `LitElement` indirectly (it's a peer dep).
  - **Mitigation:** Lit is already a peer dep of `@markommerce/frontend` (see `package.json`). `MkElement` is a thin re-export-with-defaults; not a deeper coupling.

- **Risk:** Stylelint config does not allow `@container` rule.
  - **Mitigation:** Verify and add `@container` to `stylelint.config.js` if needed in task 002 alongside the `:not(:defined)` rule deployment.

- **Risk:** Mixin registry's `defineAllComponents()` is idempotent but throws if the same tag is registered twice. If a future page imports `@markommerce/theme-blank` twice via different entry chains, the second registration throws.
  - **Mitigation:** Existing `registerBase` already guards with `if (registry.has(tagName))`. Phase 2's `components/index.ts` imports each component file once. Document the contract explicitly in the components/index.ts file header.

- **Risk:** `frontend-demo` controller's existing single `index()` action returns void and the template is implicit. Adding a primitives section requires understanding how Marko Layout maps the controller to a Latte view.
  - **Mitigation:** Task 016 reads the Layout and template-resolution code first. If it requires controller plumbing changes, those are confined to task 016 and the existing `DemoControllerTest` is updated accordingly.
