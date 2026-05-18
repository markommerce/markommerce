# markommerce/theme-blank-demo

Showcase module that renders the full set of `mk-*` primitives and form controls on a developer demo page (`/markommerce/_demo/theme-blank`). Use it to visually verify theme-blank changes, smoke-test new primitives, or evaluate styling overrides before shipping.

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

```php
// config/theme_blank_demo.php
return ['enabled' => true];
```

Then visit `/markommerce/_demo/theme-blank`.

## What's on the page

- **Layout primitives** --- `mk-stack`, `mk-cluster`, `mk-grid`, `mk-container`, `mk-sidebar`, `mk-switcher`, `mk-cover`, `mk-divider`
- **Typography primitives** --- `mk-heading`, `mk-text`, `mk-link`, `mk-badge`
- **Form controls** --- `mk-button`, `mk-input`, `mk-textarea`, `mk-select`, `mk-checkbox`, `mk-radio`, `mk-switch`, `mk-field`, `mk-fieldset`, `mk-form`
- A complete working `mk-form` wired with `mk-submit` and `mk-invalid` event listeners (open the browser console to see the payloads).

## Documentation

Full usage, screenshots, and per-component docs: [markommerce/theme-blank-demo](https://markommerce.dev/docs/packages/theme-blank-demo/)
