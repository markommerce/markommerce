---
title: markommerce/frontend-demo
description: Reference and smoke-test module for the Markommerce frontend stack --- demonstrates a working counter component with a mixin extension and typed DOM events.
---

`markommerce/frontend-demo` is the canonical reference implementation and smoke test for the Markommerce frontend stack. It is a development-only package (`require-dev`) that proves the full integration path --- component registration, mixin layering, and typed DOM events --- works end-to-end in a real browser. Use it as living documentation when building your own frontend modules.

## Installation

Install the Composer package as a development dependency:

```bash
composer require-dev markommerce/frontend-demo
```

Install the npm package:

```bash
npm install @markommerce/frontend-demo
```

## Configuration

Enable the demo route in your application config. The package ships a `config/frontend_demo.php` file that defaults the route to disabled:

```php title="config/frontend_demo.php"
<?php

declare(strict_types=1);

return [
    'enabled' => false,
];
```

Set `frontend_demo.enabled` to `true` in your local config override to activate the `GET /markommerce/_demo` route:

```php title="config/local/frontend_demo.php"
<?php

declare(strict_types=1);

return [
    'frontend_demo.enabled' => true,
];
```

## Usage

Once `frontend_demo.enabled` is set to `true` and the frontend assets are built, visit `/markommerce/_demo` in your browser. You should see:

- A **counter** component that increments its label on each button click.
- A **suffix label** appended by `LabelSuffixMixin` --- confirming that the mixin chain composites correctly onto the base class.
- A `markommerce:counter:changed` **DOM event** logged to the browser console on each increment --- confirming that `dispatchMarkommerceEvent` dispatches through the shadow boundary.

These three behaviors together constitute the full smoke test for the frontend stack.

## Registration Pattern

The demo registers its component in `packages/frontend-demo/resources/js/index.ts`. The snippet below is the verbatim source:

```typescript title="packages/frontend-demo/resources/js/index.ts"
import { registerBase, addMixin } from '@markommerce/frontend';
import { MarkommerceCounterElement } from './components/MarkommerceCounter';
import { LabelSuffixMixin } from './mixins/LabelSuffixMixin';

registerBase('markommerce-counter', MarkommerceCounterElement);
addMixin('markommerce-counter', LabelSuffixMixin, { source: '@markommerce/frontend-demo', priority: 100 });

export { MarkommerceCounterElement, LabelSuffixMixin };
```

`registerBase` declares the base Lit element class for the `markommerce-counter` tag. `addMixin` layers `LabelSuffixMixin` on top at priority 100, which overrides `renderLabel()` to append a configurable suffix string to the count display.

### The Base Component

`MarkommerceCounterElement` uses protected template methods so downstream mixins can override individual rendering slots:

```typescript title="packages/frontend-demo/resources/js/components/MarkommerceCounter.ts"
import { LitElement, html, nothing } from 'lit';
import { property, state } from 'lit/decorators.js';
import { dispatchMarkommerceEvent } from '@markommerce/frontend';
import type { TemplateResult } from 'lit';
import '../../css/components/counter.css';

declare module '@markommerce/frontend' {
  interface MarkommerceEventMap {
    'markommerce:counter:changed': { count: number };
  }
}

export class MarkommerceCounterElement extends LitElement {
  @property({ type: Number, attribute: 'start-value' }) startValue = 0;

  @state() protected count = 0;

  override createRenderRoot(): HTMLElement {
    return this;
  }

  override connectedCallback(): void {
    super.connectedCallback();
    this.count = this.startValue;
  }

  protected increment(): void {
    this.count += 1;
    dispatchMarkommerceEvent(this, 'markommerce:counter:changed', { count: this.count });
  }

  protected renderLabel(): TemplateResult {
    return html`<span class="counter__label">${this.count}</span>`;
  }

  protected renderButton(): TemplateResult {
    return html`<button class="counter__button" @click=${this.increment}>Increment</button>`;
  }

  protected renderExtras(): TemplateResult | typeof nothing {
    return nothing;
  }

  override render(): TemplateResult {
    return html`<div class="counter">${this.renderLabel()}${this.renderButton()}${this.renderExtras()}</div>`;
  }
}
```

### LabelSuffixMixin

`LabelSuffixMixin` is a functional mixin that adds a `suffix` property and overrides `renderLabel()`:

```typescript title="packages/frontend-demo/resources/js/mixins/LabelSuffixMixin.ts"
import { html } from 'lit';
import { property } from 'lit/decorators.js';
import type { TemplateResult } from 'lit';
import type { Constructor } from '@markommerce/frontend';
import type { MarkommerceCounterElement } from '../components/MarkommerceCounter';

type CounterBase = Constructor<MarkommerceCounterElement>;

export function LabelSuffixMixin<TBase extends CounterBase>(Base: TBase): TBase {
  class WithSuffix extends Base {
    @property({ type: String }) suffix = '';

    override renderLabel(): TemplateResult {
      return html`<span>${this.count}${this.suffix}</span>`;
    }
  }
  return WithSuffix as unknown as TBase;
}
```

## Consumer-App Integration

The following steps walk through the full end-to-end integration from a consuming application.

### 1. Add as a path repository

During local development, add `markommerce/frontend-demo` as a path repository in the consuming app's `composer.json`:

```json title="composer.json (consuming app)"
{
    "repositories": [
        {
            "type": "path",
            "url": "../markommerce/packages/frontend-demo"
        }
    ],
    "require-dev": {
        "markommerce/frontend-demo": "self.version"
    }
}
```

### 2. Enable the demo route

Set `frontend_demo.enabled` to `true` in the consuming app's local config:

```php title="config/local/frontend_demo.php"
<?php

declare(strict_types=1);

return [
    'frontend_demo.enabled' => true,
];
```

### 3. Set the public output directory

Tell Vite where to write the built assets by exporting `MARKOMMERCE_CONSUMER_PUBLIC`. Run this from the markommerce repo root:

```bash
export MARKOMMERCE_CONSUMER_PUBLIC=../consuming-app/public/build
```

### 4. Run the dev server

Start Vite in watch mode from the markommerce repo root:

```bash
npm run dev
```

### 5. Visit the demo page

With the consuming application running, open `/markommerce/_demo` in your browser. The counter, mixin label, and console event confirm the stack is working.

## Living Documentation

Because `markommerce/frontend-demo` is a real, runnable module rather than isolated unit tests, it validates the full integration path every time assets are rebuilt. Treat it as the canonical reference when:

- Adding a new component to the registry --- add a demo instance to the `_demo` route.
- Authoring a new mixin --- attach it to `markommerce-counter` with a higher priority and confirm the output changes.
- Debugging event dispatch --- watch the console for `markommerce:counter:changed`.

## Related Packages

- [markommerce/frontend](/docs/packages/frontend/) --- the kernel package that supplies `registerBase`, `addMixin`, `defineAllComponents`, the hooks registry, and `dispatchMarkommerceEvent`.
- [Writing a Markommerce Frontend Module](/docs/guides/writing-a-frontend-module/) --- step-by-step guide that uses `markommerce/frontend-demo` as the reference throughout.
