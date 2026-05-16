---
title: markommerce/theme-blank
description: Unstyled baseline theme for Markommerce --- design tokens, page layouts, CSS layer wiring, and a stub JS API ready for extension.
---

`markommerce/theme-blank` is the foundational theme layer for Markommerce storefronts. It ships a complete set of semantic design tokens (the `--mk-*` namespace), a CSS reset and base styles, five server-rendered page layout templates, a `layouts.css` that establishes grid structures inside `@layer theme`, and a JavaScript API stub (`showToast`, `openModal`) whose real behavior arrives in Phase 4. The package is designed to be overridden wholesale or incrementally --- every token, every layout block, and every CSS layer is a documented extension point.

## Installation

Install the Composer package:

```bash
composer require markommerce/theme-blank
```

Install the npm package (peer dependencies `lit` and `open-props` are required):

```bash
npm install @markommerce/theme-blank lit open-props
```

## Design Tokens

All `markommerce/theme-blank` tokens use the `--mk-*` namespace to avoid collisions with custom properties from other systems. Tokens are declared inside `@layer tokens` in `tokens.css`, which means any rule in `@layer theme` or higher --- including unlayered consumer CSS --- can override them without `!important`.

The consumer must load [Open Props](https://open-props.style/) (`open-props/style.css`) before or alongside this file. Open Props ships unlayered, so its raw scale variables (`--blue-6`, `--size-4`, etc.) are always resolved before the `--mk-*` tokens that reference them.

### Colors

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-color-primary` | `var(--blue-6)` | Primary brand color for buttons, links, and interactive elements |
| `--mk-color-primary-light` | `var(--blue-4)` | Lighter primary tint for backgrounds and badges |
| `--mk-color-primary-hover` | `var(--blue-7)` | Primary color on hover |
| `--mk-color-primary-active` | `var(--blue-8)` | Primary color in active/pressed state |
| `--mk-color-on-primary` | `var(--gray-0)` | Text or icon color placed on top of primary |
| `--mk-color-surface` | `var(--gray-0)` | Default card and container background |
| `--mk-color-on-surface` | `var(--gray-9)` | Default text color on surfaces |
| `--mk-color-border` | `var(--gray-3)` | Default border color |
| `--mk-color-error` | `var(--red-6)` | Error state color |
| `--mk-color-fg` | `var(--gray-9)` | Foreground (text) color |
| `--mk-color-bg` | `var(--gray-0)` | Page background color |
| `--mk-color-fg-muted` | `var(--gray-6)` | Muted or secondary text |
| `--mk-color-success` | `var(--green-6)` | Success state color |
| `--mk-color-warning` | `var(--yellow-6)` | Warning state color |
| `--mk-color-danger` | `var(--red-6)` | Danger/destructive action color |
| `--mk-color-info` | `var(--blue-5)` | Informational state color |

Dark-mode overrides activate automatically when `data-theme="dark"` is set on any ancestor element. The following tokens flip in dark mode:

| Token | Dark value |
| --- | --- |
| `--mk-color-primary` | `var(--blue-4)` |
| `--mk-color-surface` | `var(--gray-9)` |
| `--mk-color-on-surface` | `var(--gray-1)` |
| `--mk-color-border` | `var(--gray-7)` |
| `--mk-color-fg` | `var(--gray-1)` |
| `--mk-color-bg` | `var(--gray-9)` |

### Spacing

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-space-0` | `0` | Zero spacing (no Open Props equivalent) |
| `--mk-space-1` | `var(--size-1)` | Extra-small spacing |
| `--mk-space-2` | `var(--size-2)` | Small spacing |
| `--mk-space-3` | `var(--size-3)` | Medium-small spacing |
| `--mk-space-4` | `var(--size-4)` | Base spacing |
| `--mk-space-5` | `var(--size-5)` | Medium-large spacing |
| `--mk-space-6` | `var(--size-6)` | Large spacing |
| `--mk-space-7` | `var(--size-7)` | Extra-large spacing |
| `--mk-space-8` | `var(--size-8)` | 2x large spacing |
| `--mk-space-9` | `var(--size-9)` | 3x large spacing |

### Typography

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-font-sans` | `var(--font-sans)` | System sans-serif font stack |
| `--mk-font-mono` | `var(--font-mono)` | Monospace font stack |
| `--mk-font-size-xs` | `var(--font-size-00)` | Extra-small text |
| `--mk-font-size-sm` | `var(--font-size-0)` | Small text |
| `--mk-font-size-base` | `var(--font-size-1)` | Base body text size |
| `--mk-font-size-lg` | `var(--font-size-2)` | Large text |
| `--mk-font-size-xl` | `var(--font-size-3)` | Extra-large text |
| `--mk-font-size-2xl` | `var(--font-size-4)` | 2x large heading |
| `--mk-font-size-3xl` | `var(--font-size-5)` | 3x large heading |
| `--mk-font-weight-normal` | `400` | Regular font weight |
| `--mk-font-weight-medium` | `500` | Medium font weight |
| `--mk-font-weight-bold` | `700` | Bold font weight |
| `--mk-line-height-tight` | `var(--font-lineheight-1)` | Tight line height for headings |
| `--mk-line-height-normal` | `var(--font-lineheight-3)` | Normal body line height |
| `--mk-line-height-loose` | `var(--font-lineheight-4)` | Loose line height for readable prose |

### Radii

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-radius-sm` | `var(--radius-2)` | Small border radius (inputs, badges) |
| `--mk-radius-base` | `var(--radius-3)` | Default border radius (cards, buttons) |
| `--mk-radius-lg` | `var(--radius-4)` | Large border radius (modals, dialogs) |
| `--mk-radius-full` | `var(--radius-round)` | Fully rounded (pills, avatars) |

### Shadows

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-shadow-sm` | `var(--shadow-1)` | Subtle elevation (dropdowns, tooltips) |
| `--mk-shadow-md` | `var(--shadow-2)` | Medium elevation (cards, popovers) |
| `--mk-shadow-lg` | `var(--shadow-3)` | High elevation (modals, drawers) |

### Motion

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-transition-fast` | `150ms ease` | Fast micro-interaction transitions |
| `--mk-transition-base` | `250ms ease` | Default UI transition duration |
| `--mk-duration-normal` | `250ms` | Standard animation duration |
| `--mk-duration-slow` | `500ms` | Slow animation duration |
| `--mk-ease-out` | `var(--ease-out-2)` | Ease-out easing curve |
| `--mk-ease-in-out` | `var(--ease-in-out-3)` | Ease-in-out easing curve |

### Breakpoints

Breakpoints are declared as `@custom-media` at the top level of `tokens.css` (outside any `@layer` block, as required by the CSS Cascading and Layers spec). They are inlined at build time by `postcss-custom-media`.

| Token | Value | Purpose |
| --- | --- | --- |
| `--mk-breakpoint-sm` | `(min-width: 640px)` | Small viewport breakpoint |
| `--mk-breakpoint-md` | `(min-width: 768px)` | Medium viewport breakpoint (tablet) |
| `--mk-breakpoint-lg` | `(min-width: 1024px)` | Large viewport breakpoint (desktop) |
| `--mk-breakpoint-xl` | `(min-width: 1280px)` | Extra-large viewport breakpoint (wide) |

Use breakpoints in CSS via `@media (--mk-breakpoint-md) { ... }`.

## CSS Layers

`markommerce/theme-blank` participates in the cascade-layer contract established by `@markommerce/frontend/css/layers.css`. That file declares the full layer order upfront:

```css title="packages/frontend/resources/css/layers.css"
@layer reset, tokens, base, components, modules, theme, utilities;
```

This package contributes to three of those layers:

| Layer | File | Contents |
| --- | --- | --- |
| `tokens` | `@markommerce/theme-blank/css/tokens.css` | All `--mk-*` custom properties |
| `base` | `@markommerce/theme-blank/css/base.css` | Box-sizing reset, body/heading/link/image defaults |
| `theme` | `@markommerce/theme-blank/css/layouts.css` | Page layout grid structures |

Import all three in your entry point after the layer declaration:

```typescript title="resources/js/main.ts"
// 1. Establish cascade-layer order (lowest → highest)
import '@markommerce/frontend/css/layers.css';
// 2. Open Props raw token scale (unlayered — must load before semantic tokens)
import 'open-props/style.css';
// 3. Semantic design tokens — inside @layer tokens
import '@markommerce/theme-blank/css/tokens.css';
// 4. Base reset and element defaults — inside @layer base
import '@markommerce/theme-blank/css/base.css';
// 5. Page layout grid structures — inside @layer theme
import '@markommerce/theme-blank/css/layouts.css';
```

To override `--mk-*` tokens, write your overrides inside `@layer theme`:

```css
@layer theme {
  :root {
    --mk-color-primary: var(--violet-6);
    --mk-color-primary-hover: var(--violet-7);
    --mk-color-on-primary: var(--gray-0);
  }
}
```

To override raw Open Props variables (e.g., to swap the entire color palette), write outside any `@layer` block --- unlayered CSS has higher precedence than any layered rule:

```css
/* Unlayered — overrides Open Props everywhere */
:root {
  --blue-6: #6d28d9;
}
```

## Page Layouts

`markommerce/theme-blank` ships six Latte layout templates in `resources/views/layout/`. Each layout extends the chain and fills named blocks.

### Layout Reference

| Layout | Template path | Blocks exposed |
| --- | --- | --- |
| `base` | `theme-blank::layout/base` | `title`, `head-extra`, `body`, `header`, `main`, `footer` |
| `empty` | `theme-blank::layout/empty` | `content` (no header/footer chrome) |
| `1column` | `theme-blank::layout/1column` | `content` (centered max-width wrapper) |
| `2columns-left` | `theme-blank::layout/2columns-left` | `sidebar-left`, `content` |
| `2columns-right` | `theme-blank::layout/2columns-right` | `content`, `sidebar-right` |
| `3columns` | `theme-blank::layout/3columns` | `sidebar-left`, `content`, `sidebar-right` |

### Base Layout

`base.latte` is the outermost shell. It provides the `<!doctype html>`, `<html>`, `<head>`, and `<body>` structure including the `{vite()}` asset injection call. All other layouts extend it.

### Empty Layout

`empty.latte` overrides `{block body}` entirely, discarding the parent's `header`/`main`/`footer` chrome. Use it for error pages, login screens, install wizards, or any page that does not want the standard site structure. Note: because `{block body}` is overridden, the `header`, `main`, and `footer` blocks from `base.latte` are not available for further override when this layout is used.

### Content Layouts

The `1column`, `2columns-left`, `2columns-right`, and `3columns` layouts extend `base.latte` and override `{block main}` with the appropriate CSS grid wrapper. Use them by declaring `{layout}` at the top of any Latte template:

```latte
{layout 'theme-blank::layout/1column'}

{block content}
  <h1>Welcome</h1>
  <p>Your page content goes here.</p>
{/block}
```

```latte
{layout 'theme-blank::layout/2columns-left'}

{block sidebar-left}
  <nav>Category navigation</nav>
{/block}

{block content}
  <h1>Category: Shirts</h1>
  {* product grid *}
{/block}
```

```latte
{layout 'theme-blank::layout/3columns'}

{block sidebar-left}
  <nav>Filters</nav>
{/block}

{block content}
  <h1>Search Results</h1>
{/block}

{block sidebar-right}
  <aside>Promotions</aside>
{/block}
```

The two-column layouts switch from a single-column stacked view to the side-by-side grid at `--mk-breakpoint-md` (768 px). The three-column layout switches at `--mk-breakpoint-lg` (1024 px).

## JS API

`@markommerce/theme-blank` exports a stub JavaScript API from its main entry point. The stubs log a `console.warn` and return sensible no-op values. Real implementations land in Phase 4.

:::caution[Phase 4 stubs]
`showToast()` and `openModal()` are stubs in Phase 1. They emit a `console.warn` so callers can detect the stub in development. Do not ship code that depends on their behavior before Phase 4.
:::

### `showToast(message, options?)`

```typescript
import { showToast } from '@markommerce/theme-blank';
import type { ToastOptions } from '@markommerce/theme-blank';

showToast('Item added to cart');
showToast('Payment failed', { variant: 'danger', duration: 5000 });
```

**Signature:** `showToast(message: string, options?: ToastOptions): void`

**`ToastOptions`:**

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `variant` | `'info' \| 'success' \| 'warning' \| 'danger'` | — | Visual style of the toast |
| `duration` | `number` | — | Display duration in milliseconds |

### `openModal(content, options?)`

```typescript
import { openModal } from '@markommerce/theme-blank';
import type { ModalOptions, ModalHandle } from '@markommerce/theme-blank';

const handle: ModalHandle = openModal('<p>Confirm deletion?</p>', {
  dismissible: true,
  size: 'sm',
});

// Close programmatically:
handle.close();
```

**Signature:** `openModal(content: HTMLElement | string, options?: ModalOptions): ModalHandle`

**`ModalOptions`:**

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `dismissible` | `boolean` | — | Whether clicking the backdrop closes the modal |
| `size` | `'sm' \| 'md' \| 'lg'` | — | Modal width preset |

**`ModalHandle`:**

| Method | Signature | Description |
| --- | --- | --- |
| `close` | `(): void` | Closes the modal and removes it from the DOM |

## Extending the Theme

### Override `--mk-*` Tokens

Reskin the theme by overriding design tokens inside `@layer theme`. Your overrides win because `theme` sits above `tokens` in the layer order.

```css title="resources/css/brand.css"
@layer theme {
  :root {
    /* Swap the primary brand color */
    --mk-color-primary: var(--violet-6);
    --mk-color-primary-light: var(--violet-4);
    --mk-color-primary-hover: var(--violet-7);
    --mk-color-primary-active: var(--violet-8);

    /* Adjust the base type scale */
    --mk-font-size-base: var(--font-size-2);
    --mk-line-height-normal: var(--font-lineheight-4);
  }
}
```

Import `brand.css` after `layouts.css` in your entry point so the override is included in the same build graph.

### Register Component Mixins

When Phase 2+ component packages arrive, downstream packages and themes register behavior mixins via `addMixin()` from `@markommerce/frontend`. The mixin attaches to an already-registered base class at a given priority --- lower priority values run first (i.e., execute closer to the base class).

```typescript title="resources/js/index.ts"
import { addMixin } from '@markommerce/frontend';
import type { Constructor } from '@markommerce/frontend';

// Example: attach a mixin to the mk-button component once it is registered
// by @markommerce/theme-blank in Phase 2.
addMixin(
  'mk-button',
  (Base: Constructor<HTMLElement>) =>
    class extends Base {
      override connectedCallback() {
        super.connectedCallback?.();
        this.setAttribute('data-brand', 'my-store');
      }
    },
  { source: '@my-store/theme', priority: 50 },
);
```

The `markommerceModuleScanner` Vite plugin discovers extension entry points via the `markommerce.extension` field in each package's `package.json` and assembles them in priority order before `defineAllComponents()` is called. See [markommerce/frontend](/docs/packages/frontend/) for the full API.

## Web Vitals

### CLS-Prevention Architectural Rule

Every component in `markommerce/theme-blank` must render correctly without JavaScript. The Lit class is a behavior layer on top of canonical server-rendered HTML. Components style themselves via plain CSS targeting the custom-element selector inside `@layer components`. JS does not replace children; it augments behavior and binds events.

This means a component's initial paint --- as served by the PHP/Latte template --- must be visually complete and correctly laid out before any JavaScript executes. Layout shift is impossible when there is no layout change between the server-rendered state and the JS-enhanced state.

### `:not(:defined)` Safety Net

`base.css` includes a CLS safety-net convention: when Phase 2+ custom elements (`mk-button`, `mk-input`, etc.) are added, `base.css` will carry a rule of the form:

```css
@layer base {
  mk-button:not(:defined),
  mk-input:not(:defined) {
    visibility: hidden;
  }
}
```

The `:not(:defined)` pseudo-class matches any custom element whose constructor has not yet been registered via `customElements.define()`. Hiding undefined elements prevents a Flash-of-Undefined-Custom-Element (FOUCE) --- a brief moment where the element's children appear unstyled before the component's styles load. Phase 1 ships no components, so the rule is intentionally absent from the current `base.css`.

### Playwright Smoke Test

Task 007 ships a Playwright CLS smoke test at `packages/theme-blank/tests/Browser/cls.spec.ts`. The test:

1. Attaches a `PerformanceObserver` to collect `layout-shift` entries.
2. Loads a static fixture HTML file (`tests/Browser/fixtures/base-page.html`) that inlines `tokens.css`, `base.css`, and `layouts.css` with no external network requests.
3. Waits 500 ms for idle.
4. Sums all `layout-shift` entry values (ignoring entries with `hadRecentInput`) and asserts the total is `0`.

Run the CLS smoke test separately from the unit suite:

```bash
npm run test:cls
```

The root `composer test:all` script is PHP-only and does not invoke Playwright. The test surface is deliberately split so CLS tests can run against a real Chromium without adding browser dependencies to the PHP CI environment.

### CI Setup

Install Chromium for Playwright in CI before running `test:cls`:

```bash
npx playwright install --with-deps chromium
npm run test:cls
```

### Adding Fixtures in Phase 2+

Each new component or layout variant that ships in Phase 2–5 should have a corresponding fixture HTML file in `tests/Browser/fixtures/`. See `packages/theme-blank/tests/Browser/README.md` for the convention. The `cls.spec.ts` test file can load multiple fixtures by iterating over the directory.

## Related Packages

- [markommerce/frontend](/docs/packages/frontend/) --- the kernel that provides the component registry (`registerBase`, `addMixin`, `defineAllComponents`), the hooks registry, `dispatchMarkommerceEvent`, and the cascade-layer declaration file.
- [markommerce/frontend-demo](/docs/packages/frontend-demo/) --- reference implementation and smoke test for the full frontend stack, using `markommerce-counter` as the canonical example component.
