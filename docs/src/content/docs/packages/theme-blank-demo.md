---
title: markommerce/theme-blank-demo
description: Developer demo page for the markommerce/theme-blank primitives and form controls.
---

`markommerce/theme-blank-demo` renders the full set of `mk-*` layout primitives and form controls on a self-contained developer demo page at `/markommerce/_demo/theme-blank`. Use it to visually verify theme-blank changes, smoke-test new primitives, or evaluate styling overrides before shipping.

## Installation

Install as a dev-only dependency:

```bash
composer require-dev markommerce/theme-blank-demo
```

Install the npm package:

```bash
npm install @markommerce/theme-blank-demo
```

Enable the route in your config (it ships disabled by default):

```php title="config/theme_blank_demo.php"
<?php

declare(strict_types=1);

return [
    'enabled' => true,
];
```

## Usage

Once `theme_blank_demo.enabled` is set to `true` and the frontend assets are built, visit `/markommerce/_demo/theme-blank` in your browser.

:::note[Screenshots]
Screenshots of the demo page will be added here once the package reaches a stable visual state.
:::

## What's on the page

### Layout primitives

`mk-stack`, `mk-cluster`, `mk-grid`, `mk-container`, `mk-sidebar`, `mk-switcher`, `mk-cover`, `mk-divider`

### Typography primitives

`mk-heading`, `mk-text`, `mk-link`, `mk-badge`

### Form controls

`mk-button`, `mk-input`, `mk-textarea`, `mk-select`, `mk-checkbox`, `mk-radio`, `mk-switch`, `mk-field`, `mk-fieldset`, `mk-form`

A complete working `mk-form` wired with `mk-submit` and `mk-invalid` event listeners is included on the page. Open the browser console to see the event payloads.

## Architecture

This package is the *theme* demo. It split from `markommerce/frontend-demo` so that each package has a focused scope:

- **`markommerce/theme-blank-demo`** (this package) --- renders the `mk-*` visual component library. Its demo page (`/markommerce/_demo/theme-blank`) covers every layout primitive, typography primitive, and form control.
- **`markommerce/frontend-demo`** --- the *kernel* demo. Its demo page (`/markommerce/_demo`) is a smoke test for the `@markommerce/frontend` kernel: custom-element registry, mixin chain, and typed DOM event dispatch via `dispatchMarkommerceEvent`.

The two packages are independent dev dependencies. You can install only the one relevant to your current task.

## Related

- [markommerce/theme-blank](/docs/packages/theme-blank/) --- the package that ships the `mk-*` primitives and form controls being demoed.
- [markommerce/frontend-demo](/docs/packages/frontend-demo/) --- the kernel reference implementation and smoke test.
- [markommerce/frontend](/docs/packages/frontend/) --- the kernel package (`registerBase`, `addMixin`, `defineAllComponents`, `dispatchMarkommerceEvent`).
