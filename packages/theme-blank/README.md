# markommerce/theme-blank

Minimal blank theme for Markommerce --- design tokens, cascade-layer scaffolding, and layout templates with no visual opinions.

Provides the `--mk-*` CSS custom property vocabulary, a set of Latte layout templates (`1column`, `2columns-left`, `2columns-right`, `3columns`, `empty`), and a thin JS API surface for toasts and modals that downstream themes wire up.

## Installation

```bash
composer require markommerce/theme-blank
```

```bash
npm install @markommerce/theme-blank
```

## Quick Example

```latte
{* extend the 1-column layout in your template *}
{layout 'theme-blank::layout/1column'}

{block content}
  <p>Hello, Markommerce!</p>
{/block}
```

Override the primary brand colour from your own theme layer:

```css
@layer theme {
  :root {
    --mk-color-primary: #0057b7;
  }
}
```

## Documentation

Full usage, API reference, and examples: [markommerce/theme-blank](https://markommerce.dev/docs/packages/theme-blank/)
