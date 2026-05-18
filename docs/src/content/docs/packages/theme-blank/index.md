---
title: markommerce/theme-blank
description: Unstyled baseline theme for Markommerce --- design tokens, page layouts, CSS layer wiring, and a full set of feedback components.
---

`markommerce/theme-blank` is the foundational theme layer for Markommerce storefronts. It ships a complete set of semantic design tokens (the `--mk-*` namespace), a CSS reset and base styles, five server-rendered page layout templates, a `layouts.css` that establishes grid structures inside `@layer theme`, and a full JavaScript API (`showToast`, `openModal`, `openDrawer`) backed by real custom-element implementations. The package is designed to be overridden wholesale or incrementally --- every token, every layout block, and every CSS layer is a documented extension point.

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
| `--mk-color-danger-hover` | `var(--red-7)` | Danger color on hover |
| `--mk-color-info` | `var(--blue-5)` | Informational state color |
| `--mk-color-focus-ring` | `color-mix(in srgb, var(--mk-color-primary) 35%, transparent)` | Focus-ring color for `:focus-visible` outlines on all interactive form elements |

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
| `--mk-radius-input` | `var(--radius-2)` | Border radius for form inputs, selects, and textareas |

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

### Form Controls

Tokens shared across `mk-input`, `mk-textarea`, `mk-select`, `mk-button`, `mk-field`, and `mk-fieldset`. Declare overrides inside `@layer theme` to reskin all form controls at once.

**Input surface:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-input-height-sm` | `2rem` | Height for small-size inputs and selects |
| `--mk-input-height-base` | `2.5rem` | Default input and select height |
| `--mk-input-height-lg` | `3rem` | Height for large-size inputs and selects |
| `--mk-input-padding-inline` | `var(--mk-space-3)` | Horizontal padding inside inputs, selects, and textareas |
| `--mk-input-bg` | `var(--mk-color-bg)` | Background color of input controls |
| `--mk-input-color` | `var(--mk-color-fg)` | Text color inside input controls |
| `--mk-input-color-placeholder` | `var(--mk-color-fg-muted)` | Placeholder text color |
| `--mk-input-border-color` | `var(--mk-color-border)` | Default border color for input controls |
| `--mk-input-border-color-hover` | `var(--gray-5)` | Border color when an input is hovered |
| `--mk-input-border-color-focus` | `var(--mk-color-primary)` | Border color when an input is focused |
| `--mk-input-border-color-error` | `var(--mk-color-error)` | Border color when an input is invalid |

**Button surface:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-button-radius` | `var(--mk-radius-base)` | Border radius for buttons |
| `--mk-button-font-weight` | `var(--mk-font-weight-medium)` | Font weight for button labels |

**Field layout:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-field-gap` | `var(--mk-space-1)` | Gap between label, control, hint, and error inside `mk-field` and `mk-fieldset` |
| `--mk-field-error-color` | `var(--mk-color-error)` | Text color for inline validation error messages |
| `--mk-field-hint-color` | `var(--mk-color-fg-muted)` | Text color for field hint text |

No dark-mode overrides are declared for input tokens. Input controls inherit dark-mode colors from `--mk-color-bg` (page background) and `--mk-color-border` (border), which flip in the main dark-mode block.

### Feedback components

Tokens for `mk-alert`, `mk-toast`, `mk-spinner`, `mk-skeleton`, `mk-modal`, and `mk-drawer`. Declare overrides inside `@layer theme` to reskin all feedback components at once.

**Alert:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-alert-bg-info` | `var(--blue-1)` | Alert background for `info` variant |
| `--mk-alert-bg-success` | `var(--green-1)` | Alert background for `success` variant |
| `--mk-alert-bg-warning` | `var(--yellow-1)` | Alert background for `warning` variant |
| `--mk-alert-bg-danger` | `var(--red-1)` | Alert background for `danger` variant |
| `--mk-alert-fg-info` | `var(--blue-9)` | Alert foreground for `info` variant |
| `--mk-alert-fg-success` | `var(--green-9)` | Alert foreground for `success` variant |
| `--mk-alert-fg-warning` | `var(--yellow-9)` | Alert foreground for `warning` variant |
| `--mk-alert-fg-danger` | `var(--red-9)` | Alert foreground for `danger` variant |
| `--mk-alert-border-color-info` | `var(--blue-3)` | Alert border for `info` variant |
| `--mk-alert-border-color-success` | `var(--green-3)` | Alert border for `success` variant |
| `--mk-alert-border-color-warning` | `var(--yellow-3)` | Alert border for `warning` variant |
| `--mk-alert-border-color-danger` | `var(--red-3)` | Alert border for `danger` variant |
| `--mk-alert-radius` | `var(--mk-radius-base)` | Border radius |
| `--mk-alert-padding` | `var(--mk-space-4)` | Internal padding |

**Toast:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-toast-bg` | `var(--mk-color-surface)` | Toast background color |
| `--mk-toast-fg` | `var(--mk-color-on-surface)` | Toast foreground (text) color |
| `--mk-toast-padding` | `var(--mk-space-3)` | Inner padding |
| `--mk-toast-radius` | `var(--mk-radius-base)` | Border radius |
| `--mk-toast-shadow` | `var(--mk-shadow-lg)` | Box shadow |
| `--mk-toast-min-width` | `18rem` | Minimum width |
| `--mk-toast-max-width` | `24rem` | Maximum width |
| `--mk-toast-region-inset` | `var(--mk-space-4)` | Distance from bottom and right viewport edges |
| `--mk-toast-region-gap` | `var(--mk-space-2)` | Gap between stacked toasts |

**Spinner:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-spinner-size-sm` | `1rem` | Diameter of the small variant |
| `--mk-spinner-size-base` | `1.5rem` | Diameter of the default variant |
| `--mk-spinner-size-lg` | `2.5rem` | Diameter of the large variant |
| `--mk-spinner-thickness` | `2px` | Border width of the spinner ring |
| `--mk-spinner-color` | `var(--mk-color-primary)` | Color of the animated arc |
| `--mk-spinner-duration` | `var(--mk-duration-slow)` | Duration of one full rotation |

**Skeleton:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-skeleton-bg` | `var(--gray-2)` | Base shimmer background color |
| `--mk-skeleton-shimmer-color` | `var(--gray-0)` | Highlight color of the shimmer sweep |
| `--mk-skeleton-radius` | `var(--mk-radius-base)` | Border radius for `text` and `rect` variants |
| `--mk-skeleton-duration` | `var(--mk-duration-slow)` | Duration of one shimmer cycle |

**Modal:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-modal-bg` | `var(--mk-color-surface)` | Dialog background color |
| `--mk-modal-fg` | `var(--mk-color-on-surface)` | Dialog foreground (text) color |
| `--mk-modal-radius` | `var(--mk-radius-lg)` | Dialog border radius |
| `--mk-modal-padding` | `var(--mk-space-5)` | Dialog inner padding |
| `--mk-modal-shadow` | `var(--mk-shadow-lg)` | Dialog box shadow |
| `--mk-modal-backdrop-color` | `color-mix(in srgb, var(--gray-9) 60%, transparent)` | `::backdrop` background color |
| `--mk-modal-width-sm` | `24rem` | Width when `size="sm"` |
| `--mk-modal-width-md` | `32rem` | Width when `size="md"` |
| `--mk-modal-width-lg` | `48rem` | Width when `size="lg"` |

**Drawer:**

| Token | Default value | Purpose |
| --- | --- | --- |
| `--mk-drawer-bg` | `var(--mk-color-surface)` | Drawer background color |
| `--mk-drawer-fg` | `var(--mk-color-on-surface)` | Drawer foreground (text) color |
| `--mk-drawer-padding` | `var(--mk-space-5)` | Drawer inner padding |
| `--mk-drawer-shadow` | `var(--mk-shadow-lg)` | Drawer box shadow |
| `--mk-drawer-width-sm` | `20rem` | Width when `size="sm"` |
| `--mk-drawer-width-md` | `28rem` | Width when `size="md"` (also the default) |
| `--mk-drawer-width-lg` | `40rem` | Width when `size="lg"` |

Dark-mode overrides for feedback tokens (activate when `data-theme="dark"` is set):

| Token | Dark value |
| --- | --- |
| `--mk-toast-bg` | `var(--gray-8)` |
| `--mk-modal-bg` | `var(--gray-8)` |
| `--mk-skeleton-bg` | `var(--gray-7)` |

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

## Components

`markommerce/theme-blank` ships a set of light-DOM custom-element primitives. Each primitive is purely presentational --- it wraps your server-rendered HTML with consistent styling, never replacing or restructuring children. All visual behavior is driven by CSS attribute selectors, so every primitive produces zero CLS even before its JavaScript is loaded.

A live demo page rendering all primitives and form controls is available at `/markommerce/_demo/theme-blank` via the [`markommerce/theme-blank-demo`](/docs/packages/theme-blank-demo/) package.

### Layout primitives

| Component | Description |
| --- | --- |
| [mk-stack](/docs/packages/theme-blank/mk-stack/) | Vertical rhythm with consistent gap |
| [mk-cluster](/docs/packages/theme-blank/mk-cluster/) | Horizontal flex-wrap row for button rows, tag lists |
| [mk-grid](/docs/packages/theme-blank/mk-grid/) | Responsive auto-fit grid driven by a minimum column width |
| [mk-container](/docs/packages/theme-blank/mk-container/) | Max-width content container with auto inline margins |
| [mk-sidebar](/docs/packages/theme-blank/mk-sidebar/) | Sidebar + main with implicit-flexbox collapse |
| [mk-switcher](/docs/packages/theme-blank/mk-switcher/) | Row→column switch via flex-basis arithmetic |
| [mk-cover](/docs/packages/theme-blank/mk-cover/) | Header / centered main / footer full-height frame |
| [mk-divider](/docs/packages/theme-blank/mk-divider/) | Styled separator with auto-injected `role="separator"` |

### Typography primitives

| Component | Description |
| --- | --- |
| [mk-heading](/docs/packages/theme-blank/mk-heading/) | Single-tag heading with ARIA role + level (two-tag form supported for SEO-critical content) |
| [mk-text](/docs/packages/theme-blank/mk-text/) | Single-tag body text with body / lead / small / muted variants (two-tag form supported for nested content) |
| [mk-link](/docs/packages/theme-blank/mk-link/) | Wrapper around native `<a>` with variant + underline-policy control |
| [mk-badge](/docs/packages/theme-blank/mk-badge/) | Inline-flex status badge with semantic variants |

### Form controls

| Component | Description |
| --- | --- |
| [mk-button](/docs/packages/theme-blank/mk-button/) | Native `<button>` wrapper with variant, size, and loading attributes |
| [mk-input](/docs/packages/theme-blank/mk-input/) | Native `<input>` wrapper with variant and size |
| [mk-textarea](/docs/packages/theme-blank/mk-textarea/) | Native `<textarea>` wrapper with variant and size |
| [mk-select](/docs/packages/theme-blank/mk-select/) | Native `<select>` wrapper with custom arrow and variant/size |
| [mk-checkbox](/docs/packages/theme-blank/mk-checkbox/) | Native checkbox wrapper with size attribute |
| [mk-radio](/docs/packages/theme-blank/mk-radio/) | Native radio wrapper with size attribute |
| [mk-switch](/docs/packages/theme-blank/mk-switch/) | Toggle-pill checkbox wrapper with auto `role="switch"` |
| [mk-field](/docs/packages/theme-blank/mk-field/) | Label/control/hint/error orchestrator with 3-layer validation |
| [mk-fieldset](/docs/packages/theme-blank/mk-fieldset/) | Native `<fieldset>` wrapper with consistent layout |
| [mk-form](/docs/packages/theme-blank/mk-form/) | Form orchestrator with novalidate + async validation + mk-submit/mk-invalid events |

### Feedback

| Component | Description |
| --- | --- |
| [mk-alert](/docs/packages/theme-blank/mk-alert/) | Inline alert banner with semantic variant styles and optional dismissibility |
| [mk-toast](/docs/packages/theme-blank/mk-toast/) | Transient notification that auto-dismisses after a configurable duration |
| [mk-spinner](/docs/packages/theme-blank/mk-spinner/) | CSS-animated loading spinner with size variants and built-in screen reader support |
| [mk-skeleton](/docs/packages/theme-blank/mk-skeleton/) | Animated shimmer placeholder for loading states |
| [mk-modal](/docs/packages/theme-blank/mk-modal/) | Modal dialog wrapping the native `<dialog>` element with open/close management |
| [mk-drawer](/docs/packages/theme-blank/mk-drawer/) | Slide-in drawer panel wrapping the native `<dialog>` element with placement support |

## JS API

`@markommerce/theme-blank` exports a JavaScript API from its main entry point, backed by real custom-element implementations.

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

### `openDrawer(content, options?)`

```typescript
import { openDrawer } from '@markommerce/theme-blank';
import type { DrawerOptions, DrawerHandle } from '@markommerce/theme-blank';

const handle: DrawerHandle = openDrawer('<p>Filter options…</p>', {
  placement: 'left',
  size: 'md',
  dismissible: true,
});

// Close programmatically:
handle.close();
```

**Signature:** `openDrawer(content: HTMLElement | string, options?: DrawerOptions): DrawerHandle`

**`DrawerOptions`:**

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `placement` | `'left' \| 'right'` | `'right'` | Side of the viewport the drawer slides in from |
| `dismissible` | `boolean` | — | Whether clicking the backdrop closes the drawer |
| `size` | `'sm' \| 'md' \| 'lg'` | — | Drawer width preset |

**`DrawerHandle`:**

| Method | Signature | Description |
| --- | --- | --- |
| `close` | `(): void` | Closes the drawer and removes it from the DOM |

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

`base.css` includes a grouped `:not(:defined)` selector covering all 28 custom element tag names (12 layout and typography primitives, 10 form controls, and 6 feedback components) inside `@layer base`. The selector exists as a documentation hook and downstream override point --- it currently carries no declarations because every component's real layout is supplied by `@layer components` tag selectors, which apply regardless of whether the element's JavaScript constructor has been registered.

```css
@layer base {
  mk-stack:not(:defined),
  mk-cluster:not(:defined),
  mk-grid:not(:defined),
  mk-container:not(:defined),
  mk-sidebar:not(:defined),
  mk-switcher:not(:defined),
  mk-cover:not(:defined),
  mk-divider:not(:defined),
  mk-heading:not(:defined),
  mk-text:not(:defined),
  mk-link:not(:defined),
  mk-badge:not(:defined),
  mk-button:not(:defined),
  mk-input:not(:defined),
  mk-textarea:not(:defined),
  mk-select:not(:defined),
  mk-checkbox:not(:defined),
  mk-radio:not(:defined),
  mk-switch:not(:defined),
  mk-field:not(:defined),
  mk-fieldset:not(:defined),
  mk-form:not(:defined),
  mk-alert:not(:defined),
  mk-toast:not(:defined),
  mk-spinner:not(:defined),
  mk-skeleton:not(:defined),
  mk-modal:not(:defined),
  mk-drawer:not(:defined) {
    /* No declarations — @layer components tag selectors supply the real layout.
     * This block is a downstream override point. */
  }
}
```

The `:not(:defined)` pseudo-class matches any custom element whose constructor has not yet been registered via `customElements.define()`. Because the components lay themselves out via plain CSS attribute selectors at `@layer components`, all 28 custom elements render correctly on first paint with no JavaScript. Consumers who want to hide a specific element until its class is registered can add a `visibility: hidden` declaration here inside `@layer theme` (which sits above `base` in the layer order).

### Playwright Smoke Tests

Two Playwright CLS smoke tests live in `packages/theme-blank/tests/Browser/`:

- `primitives-cls.spec.ts` --- covers the 12 layout and typography primitives using `fixtures/primitives-page.html`.
- `forms-cls.spec.ts` --- covers the 10 form controls using `fixtures/forms-page.html`.

Each spec:

1. Attaches a `PerformanceObserver` to collect `layout-shift` entries.
2. Loads a static fixture HTML file that inlines tokens and component CSS with no external network requests.
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

### Adding Fixtures in Phase 4+

Each new component or layout variant that ships in subsequent phases should have a corresponding fixture HTML file in `tests/Browser/fixtures/` and a matching spec file (or an entry in an existing spec). See the existing spec files in `packages/theme-blank/tests/Browser/` for the fixture convention.

## Related Packages

- [markommerce/frontend](/docs/packages/frontend/) --- the kernel that provides the component registry (`registerBase`, `addMixin`, `defineAllComponents`), the hooks registry, `dispatchMarkommerceEvent`, and the cascade-layer declaration file.
- [markommerce/frontend-demo](/docs/packages/frontend-demo/) --- reference implementation and smoke test for the full frontend stack, using `markommerce-counter` as the canonical example component.
