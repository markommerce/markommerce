---
title: Writing a Markommerce Frontend Module
description: Step-by-step guide to building a Markommerce frontend module with Lit components, mixins, hooks, typed events, CSS layers, and Vitest tests.
---

This guide walks you through creating a Markommerce frontend module from scratch --- from scaffolding the dual-manifest package to authoring Lit components with protected template methods, wiring up mixins and hooks, declaring typed DOM events, layering CSS, and writing Vitest tests. By the end you will have a working module that integrates cleanly with the rest of the frontend extension system.

## Scaffolding the Package

A Markommerce frontend module is a Composer package that also ships JavaScript. It therefore needs both a `composer.json` (for PHP autoloading and Composer dependency resolution) and a `package.json` (for the npm workspace and Vite bundling).

### The PHP manifest

Create `packages/my-module/composer.json`:

```json title="packages/my-module/composer.json"
{
    "name": "markommerce/my-module",
    "description": "My Markommerce frontend module.",
    "license": "MIT",
    "type": "marko-module",
    "require": {
        "php": "^8.5",
        "marko/config": "self.version",
        "marko/core": "self.version",
        "markommerce/frontend": "self.version"
    },
    "require-dev": {
        "marko/testing": "self.version",
        "pestphp/pest": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "Markommerce\\MyModule\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Markommerce\\MyModule\\Tests\\": "tests/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

### The JavaScript manifest

Create `packages/my-module/package.json`:

```json title="packages/my-module/package.json"
{
    "name": "@markommerce/my-module",
    "type": "module",
    "version": "0.0.1",
    "private": true,
    "markommerce": {
        "extension": "./resources/js/index.ts",
        "priority": 1000
    },
    "dependencies": {
        "@markommerce/frontend": "*"
    },
    "peerDependencies": {
        "lit": "^3.0",
        "open-props": "^1.7"
    }
}
```

Because `packages/*` is a workspace glob in the root `package.json`, npm will resolve `@markommerce/my-module` as a workspace dependency automatically after the next `npm install`.

## The `markommerce` Block

The `markommerce` object in `package.json` is the only piece of configuration the Vite scanner plugin reads. It controls how the module participates in the frontend extension system.

| Field | Required | Description |
|---|---|---|
| `extension` | Yes | Path to the module entry point. Imported as a side-effect by the generated extensions file. |
| `priority` | No | Load order relative to other modules. Lower numbers load first. Defaults to `100`. |

The `@markommerce/frontend` kernel declares `priority: 0` so it always loads first. Module packages use `priority: 1000` or higher.

```json title="package.json (markommerce block)"
"markommerce": {
    "extension": "./resources/js/index.ts",
    "priority": 1000
}
```

## Authoring a Lit Component

Markommerce components extend `LitElement` and render to **light DOM** so that global theme styles from the `@layer components` cascade apply without extra configuration. Template methods are declared `protected` so that functional mixins can override them.

```typescript title="resources/js/components/MyCounterElement.ts"
import { LitElement, html, nothing } from 'lit';
import { property, state } from 'lit/decorators.js';
import { dispatchMarkommerceEvent } from '@markommerce/frontend';
import type { TemplateResult } from 'lit';
import '../../css/components/my-counter.css';

declare module '@markommerce/frontend' {
  interface MarkommerceEventMap {
    'my-module:counter:changed': { count: number };
  }
}

export class MyCounterElement extends LitElement {
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
    dispatchMarkommerceEvent(this, 'my-module:counter:changed', { count: this.count });
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

Key conventions:

- `createRenderRoot()` returns `this` to opt into light DOM.
- Template sections (`renderLabel`, `renderButton`, `renderExtras`) are `protected` methods that return `TemplateResult | typeof nothing`. Mixins override individual sections without touching the root `render()`.
- Events are dispatched via `dispatchMarkommerceEvent`, which sets `bubbles: true` and `composed: true` by default.

## Registering a Base via `registerBase`

Once a component is authored, register it in `resources/js/index.ts`:

```typescript title="resources/js/index.ts"
import { registerBase, addMixin } from '@markommerce/frontend';
import { MyCounterElement } from './components/MyCounterElement';
import { LabelSuffixMixin } from './mixins/LabelSuffixMixin';

registerBase('my-counter', MyCounterElement);
addMixin('my-counter', LabelSuffixMixin, { source: '@markommerce/my-module', priority: 100 });

export { MyCounterElement, LabelSuffixMixin };
```

`registerBase(tagName, BaseClass)` stores the base class in the global registry. The tag is not yet defined in `customElements` --- that happens later when `defineAllComponents()` is called (typically in `main.ts`). Calling `registerBase` twice for the same tag throws a `RegistryError`.

## Authoring a Functional Mixin via `addMixin`

A functional mixin is a function that takes a base class and returns a new class extending it. The `Constructor<T>` helper type from `@markommerce/frontend` keeps TypeScript happy.

```typescript title="resources/js/mixins/LabelSuffixMixin.ts"
import { html } from 'lit';
import { property } from 'lit/decorators.js';
import type { TemplateResult } from 'lit';
import type { Constructor } from '@markommerce/frontend';
import type { MyCounterElement } from '../components/MyCounterElement';

type CounterBase = Constructor<MyCounterElement>;

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

Register the mixin alongside the base in `index.ts` using `addMixin`:

```typescript
addMixin('my-counter', LabelSuffixMixin, { source: '@markommerce/my-module', priority: 100 });
```

The `source` string identifies which package contributed the mixin (visible via `getMixinChain`). The `priority` controls application order --- lower numbers are applied first (innermost in the prototype chain), higher numbers are applied last (outermost). The default priority is `100`.

## Registering and Consuming a Hook via `registerHook` and `runHook`

Hooks are typed pipeline points that any module can tap into. Extend `HookRegistry` via declaration merging to declare a new hook:

```typescript title="resources/js/hooks/my-module-hooks.ts"
import { registerHook, runHook } from '@markommerce/frontend';

declare module '@markommerce/frontend' {
  interface HookRegistry {
    'my-module:price:format': { payload: number; return: string };
  }
}

// Register a handler
registerHook('my-module:price:format', (amount) => `$${amount.toFixed(2)}`);

// Consume the hook elsewhere
const formatted = await runHook('my-module:price:format', 19.99);
// formatted === '$19.99'
```

Multiple handlers registered for the same hook name are called in ascending priority order. Each handler receives the return value of the previous handler as its `payload`, making hooks a lightweight transform pipeline.

## Declaring a Typed CustomEvent and Extending `MarkommerceEventMap`

Typed DOM events use declaration merging on `MarkommerceEventMap`. Declare the event map augmentation in the component file itself so it is co-located with the dispatch call:

```typescript title="resources/js/components/MyCounterElement.ts (event declaration)"
import { dispatchMarkommerceEvent } from '@markommerce/frontend';

declare module '@markommerce/frontend' {
  interface MarkommerceEventMap {
    'my-module:counter:changed': { count: number };
  }
}

// Inside the component method:
dispatchMarkommerceEvent(this, 'my-module:counter:changed', { count: this.count });
```

Because `DocumentEventMap` and `HTMLElementEventMap` both extend `MarkommerceEventMap` (declared in `@markommerce/frontend/events.ts`), TypeScript will autocomplete the event name and validate the `detail` shape everywhere you call `addEventListener` or `dispatchMarkommerceEvent`.

## Extending Core Types via `declare module` Declaration Merging

Declaration merging is the standard pattern for extending open interfaces from `@markommerce/frontend`. You have seen it for `MarkommerceEventMap` and `HookRegistry`. The pattern is always the same:

```typescript
declare module '@markommerce/frontend' {
  interface MarkommerceEventMap {
    'my-module:my-event': { myField: string };
  }
}
```

```typescript
declare module '@markommerce/frontend' {
  interface HookRegistry {
    'my-module:my-hook': { payload: string; return: string };
  }
}
```

Place these augmentations in the file that first uses the new key. This ensures the type is available wherever the file is imported and avoids duplicate declarations.

## CSS Cascade Layers

Markommerce uses a fixed, seven-layer cascade order declared in `@markommerce/frontend/css/layers.css`:

```css title="layers.css"
@layer reset, tokens, base, components, modules, theme, utilities;
```

Layer precedence runs from lowest (`reset`) to highest (`utilities`). Your component styles belong in `@layer components`. Theme overrides go in `@layer theme` or higher. Never add unlayered rules in your component CSS --- they would win over all layered rules regardless of specificity.

Write component CSS inside the `@layer components` block:

```css title="resources/css/components/my-counter.css"
@layer components {
  .counter {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2);
    color: var(--color-text);
  }

  .counter__label {
    font-size: var(--font-size-1);
    color: var(--color-primary);
    min-width: var(--size-4);
    text-align: center;
  }

  .counter__button {
    padding-inline: var(--space-2);
    padding-block: var(--space-1);
    background-color: var(--color-primary);
    color: var(--color-surface);
    border: none;
    border-radius: var(--radius-2);
    cursor: pointer;
    font-size: var(--font-size-1);

    &:hover {
      background-color: var(--color-primary-hover, var(--color-primary));
      opacity: 0.9;
    }

    &:focus-visible {
      outline: var(--border-size-2) solid var(--color-primary);
      outline-offset: var(--space-1);
    }
  }
}
```

Use Markommerce semantic tokens (`--color-primary`, `--space-2`, etc.) defined in `@markommerce/frontend/css/tokens.css` rather than raw Open Props variables. This keeps your component themeable --- a downstream consumer can override the semantic tokens inside `@layer theme` to reskin everything at once.

The `main.ts` entry point loads CSS in the correct order:

```typescript title="resources/js/main.ts"
import '@markommerce/frontend/css/layers.css';   // layer order first
import 'open-props/style.css';                   // raw tokens (unlayered)
import '@markommerce/frontend/css/tokens.css';   // semantic tokens
import './.generated/extensions';               // module side-effects
import '../css/components/my-counter.css';       // component CSS
import { defineAllComponents } from '@markommerce/frontend';
defineAllComponents();
```

## Testing with Vitest

Use Vitest with the `happy-dom` environment to unit-test components and mixins. Component tests register a unique custom element tag per test to avoid `customElements.define` conflicts across tests.

### Testing a Component

```typescript title="resources/js/components/MyCounterElement.test.ts"
// @vitest-environment happy-dom
import { describe, it, expect, afterEach } from 'vitest';
import { MyCounterElement } from './MyCounterElement';

afterEach(() => {
  document.body.innerHTML = '';
});

let tagCounter = 0;
function uniqueTag(): string {
  return `my-counter-test-${++tagCounter}`;
}

async function createCounter(attrs = ''): Promise<HTMLElement & { updateComplete: Promise<boolean> }> {
  const tag = uniqueTag();
  if (!customElements.get(tag)) {
    customElements.define(tag, class extends MyCounterElement {});
  }
  document.body.innerHTML = `<${tag} ${attrs}></${tag}>`;
  const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
  await el.updateComplete;
  return el;
}

describe('MyCounterElement', () => {
  it('renders a button with the initial count of 0 by default', async () => {
    const el = await createCounter();
    expect(el.textContent).toContain('0');
    const button = el.querySelector('button');
    expect(button).not.toBeNull();
  });

  it('increments the count on button click', async () => {
    const el = await createCounter();
    const button = el.querySelector('button') as HTMLButtonElement;
    button.click();
    await el.updateComplete;
    expect(el.textContent).toContain('1');
  });

  it('dispatches my-module:counter:changed with the new count on increment', async () => {
    const el = await createCounter();
    const button = el.querySelector('button') as HTMLButtonElement;
    let detail: { count: number } | null = null;
    el.addEventListener('my-module:counter:changed', (e: Event) => {
      detail = (e as CustomEvent<{ count: number }>).detail;
    });
    button.click();
    await el.updateComplete;
    expect(detail?.count).toBe(1);
  });
});
```

### Testing a Mixin

```typescript title="resources/js/mixins/LabelSuffixMixin.test.ts"
// @vitest-environment happy-dom
import { describe, it, expect } from 'vitest';
import { MyCounterElement } from '../components/MyCounterElement';
import { LabelSuffixMixin } from './LabelSuffixMixin';

let tagCounter = 0;
function uniqueTag(): string {
  return `suffix-mixin-test-${++tagCounter}`;
}

describe('LabelSuffixMixin', () => {
  it('appends the suffix attribute value to the rendered label', async () => {
    const tag = uniqueTag();
    const MixedElement = LabelSuffixMixin(MyCounterElement);
    customElements.define(tag, MixedElement);
    document.body.innerHTML = `<${tag} suffix=" items"></${tag}>`;
    const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
    await el.updateComplete;
    expect(el.textContent).toContain('0 items');
  });

  it('defaults suffix to an empty string when no attribute is provided', async () => {
    const tag = uniqueTag();
    const MixedElement = LabelSuffixMixin(MyCounterElement);
    customElements.define(tag, MixedElement);
    document.body.innerHTML = `<${tag}></${tag}>`;
    const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
    await el.updateComplete;
    const instance = el as unknown as { suffix: string };
    expect(instance.suffix).toBe('');
  });
});
```

Run the JavaScript tests with:

```bash
npx vitest run
```

or watch mode during development:

```bash
npx vitest
```

## Where to Look Next

The canonical reference implementation for everything in this guide is in `packages/frontend-demo/src/`. It contains:

- `packages/frontend-demo/resources/js/components/MarkommerceCounter.ts` --- the base Lit component with protected template methods and event dispatch.
- `packages/frontend-demo/resources/js/mixins/LabelSuffixMixin.ts` --- a functional mixin overriding a template method.
- `packages/frontend-demo/resources/js/index.ts` --- the extension entry point calling `registerBase` and `addMixin`.
- `packages/frontend-demo/resources/js/main.ts` --- the application entry point with correct CSS import order and `defineAllComponents()`.
- `packages/frontend-demo/resources/css/components/counter.css` --- component CSS inside `@layer components` using semantic tokens.
- `packages/frontend-demo/resources/js/components/MarkommerceCounter.test.ts` --- Vitest tests for the component.
- `packages/frontend-demo/resources/js/package.test.ts` --- package manifest and integration tests.

The `@markommerce/frontend` kernel source lives in `packages/frontend/resources/js/` and is the definitive reference for the `registry.ts`, `hooks.ts`, and `events.ts` APIs.
