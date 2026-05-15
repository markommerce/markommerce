---
title: markommerce/frontend
description: Frontend integration for Markommerce --- Latte helpers, Vite asset wiring, and JS/CSS component plumbing.
---

Frontend integration for Markommerce --- Latte helpers, Vite asset wiring, and JS/CSS component plumbing. The package ships a TypeScript component registry, a hooks registry, a type-safe DOM events helper, a CSS cascade-layer foundation, a Vite scanner plugin, and a Latte `{vite()}` function that injects the correct `<script>` and `<link>` tags for both dev-server and production builds.

## Installation

Install the Composer package:

```bash
composer require markommerce/frontend
```

Install the npm package (peer dependencies `lit` and `open-props` are required):

```bash
npm install @markommerce/frontend lit open-props
```

## Configuration

The package reads `config/vite.php` in the consuming application. Publish or create this file to override the defaults:

```php title="config/vite.php"
<?php

declare(strict_types=1);

return [
    // Entry point for the Vite build (relative to the repo root).
    'entry' => 'packages/frontend-demo/resources/js/main.ts',

    // Set to true to proxy assets through the Vite dev server instead of
    // reading the production manifest.
    'useDevServer' => false,

    // URL of the Vite dev server. Only used when useDevServer is true.
    'devServerUrl' => 'http://localhost:5173',

    // Subdirectory inside the consuming app's public/ where built assets land.
    // Must match Vite's build.outDir (minus the public/ prefix).
    'buildDirectory' => 'build',

    // Path to the Vite manifest, relative to buildDirectory.
    'manifestFilename' => '.vite/manifest.json',

    // Additional stylesheet paths to inject when useDevServer is true
    // (Vite dev server does not serve CSS links automatically).
    'devServerStylesheets' => [],
];
```

## Usage

### CSS Foundation

Import the layer declaration first in your entry point so cascade-layer precedence is established before any other stylesheet loads:

```typescript title="resources/js/main.ts"
// 1. Establish layer order (lowest → highest: reset, tokens, base, components, modules, theme, utilities)
import '@markommerce/frontend/css/layers.css';
// 2. Open Props raw tokens (unlayered --- must come before semantic tokens)
import 'open-props/style.css';
// 3. Markommerce semantic design tokens (inside @layer tokens)
import '@markommerce/frontend/css/tokens.css';
```

The semantic tokens map Open Props variables to Markommerce-specific names:

**Colors**

| Token | Default value |
| --- | --- |
| `--color-primary` | `var(--blue-6)` |
| `--color-primary-light` | `var(--blue-4)` |
| `--color-on-primary` | `var(--gray-0)` |
| `--color-surface` | `var(--gray-0)` |
| `--color-on-surface` | `var(--gray-9)` |
| `--color-border` | `var(--gray-3)` |
| `--color-error` | `var(--red-6)` |

**Spacing**

| Token | Default value |
| --- | --- |
| `--space-1` | `var(--size-1)` |
| `--space-2` | `var(--size-2)` |
| `--space-3` | `var(--size-3)` |
| `--space-4` | `var(--size-4)` |
| `--space-5` | `var(--size-5)` |

**Typography**

| Token | Default value |
| --- | --- |
| `--font-size-sm` | `var(--font-size-0)` |
| `--font-size-base` | `var(--font-size-1)` |
| `--font-size-lg` | `var(--font-size-2)` |
| `--font-size-xl` | `var(--font-size-3)` |
| `--font-weight-normal` | `400` |
| `--font-weight-bold` | `700` |

**Transitions**

| Token | Default value |
| --- | --- |
| `--transition-fast` | `150ms ease` |
| `--transition-base` | `250ms ease` |

**Radius**

| Token | Default value |
| --- | --- |
| `--radius-sm` | `var(--radius-2)` |
| `--radius-base` | `var(--radius-3)` |
| `--radius-lg` | `var(--radius-4)` |

Dark-mode overrides for colors activate automatically when a `data-theme="dark"` attribute is on any ancestor element.

Override tokens inside `@layer theme` or higher. Override raw Open Props variables outside any `@layer` block (unlayered CSS has higher precedence than layered rules).

### Component Registry

The component registry decouples custom element base classes from their mixin extensions. Modules register a base class once; other modules (or the consuming app) attach mixins at any priority before `defineAllComponents()` is called.

```typescript
import { registerBase, addMixin, defineAllComponents } from '@markommerce/frontend';

// 1. Register the base class (done once per tag, usually in the module that owns the element).
registerBase('mk-counter', class extends HTMLElement {
  connectedCallback() {
    this.textContent = '0';
  }
});

// 2. Attach a mixin from a downstream module (lower priority runs first).
addMixin(
  'mk-counter',
  (Base) => class extends Base {
    connectedCallback() {
      super.connectedCallback?.();
      this.dataset['enhanced'] = 'true';
    }
  },
  { source: 'my-theme', priority: 50 },
);

// 3. Compose all mixins and define every custom element (call once at the end of the entry point).
defineAllComponents();
```

### Hooks Registry

The hooks registry provides a typed, priority-ordered middleware pipeline for JavaScript extension points. Declare the hook shape via TypeScript declaration merging, then register handlers and run the hook.

```typescript
import { registerHook, runHook } from '@markommerce/frontend';
import type { HookRegistry } from '@markommerce/frontend';

// Extend the global registry type (declaration merging).
declare module '@markommerce/frontend' {
  interface HookRegistry {
    'cart:add': { payload: { productId: string; qty: number }; return: { productId: string; qty: number } };
  }
}

// Register a handler (lower priority runs first; default priority is 100).
registerHook('cart:add', async (payload) => {
  console.log('Adding to cart:', payload);
  return payload;
}, { priority: 10 });

// Run the hook (returns the final transformed payload).
const result = await runHook('cart:add', { productId: 'SKU-001', qty: 1 });
```

The `Hooks` convenience object exposes the same API:

```typescript
import { Hooks } from '@markommerce/frontend';

Hooks.register('cart:add', handler);
const result = await Hooks.run('cart:add', payload);
```

### DOM Events Helper

`dispatchMarkommerceEvent` wraps `CustomEvent` with sensible defaults (`bubbles: true`, `composed: true`, `cancelable: false`) and full TypeScript type inference when the event name is declared in `MarkommerceEventMap`.

```typescript
import { dispatchMarkommerceEvent } from '@markommerce/frontend';
import type { MarkommerceEventMap } from '@markommerce/frontend';

// Declare event shapes via declaration merging (usually in the module that dispatches the event).
declare module '@markommerce/frontend' {
  interface MarkommerceEventMap {
    'markommerce:cart:updated': { itemCount: number };
  }
}

// Dispatch --- TypeScript enforces the correct detail shape for known event names.
dispatchMarkommerceEvent(document, 'markommerce:cart:updated', { itemCount: 3 });

// Listen --- DocumentEventMap and HTMLElementEventMap are augmented automatically.
document.addEventListener('markommerce:cart:updated', (e) => {
  console.log(e.detail.itemCount); // typed as number
});
```

## API Reference

### Component Registry

| Function | Signature | Description |
| --- | --- | --- |
| `registerBase` | `(tagName: string, BaseClass: Constructor<HTMLElement>): void` | Register a base class for a custom element tag. Throws `RegistryError` if the tag is already registered. |
| `addMixin` | `(tagName: string, mixin: Mixin<TBase>, options: { source: string; priority?: number }): void` | Attach a mixin to a registered tag. Lower `priority` values run first (default: `100`). Throws `RegistryError` if the tag is not registered. |
| `defineAllComponents` | `(): void` | Compose all mixins onto their base classes in priority order and call `customElements.define`. Idempotent --- safe to call multiple times. |
| `getRegisteredComponents` | `(): readonly RegisteredComponent[]` | Return all registered component descriptors. |
| `getMixinChain` | `(tagName: string): readonly { source: string; priority: number }[]` | Return the sorted mixin chain for a tag. |

### Hooks Registry

| Function | Signature | Description |
| --- | --- | --- |
| `registerHook` | `<K extends keyof HookRegistry>(name: K, handler: HookHandler<K>, options?: { priority?: number }): void` | Register a hook handler. Lower `priority` values run first (default: `100`). |
| `runHook` | `<K extends keyof HookRegistry>(name: K, payload: ...) => Promise<...>` | Run all handlers for a hook in priority order. Returns the final transformed value. |

### DOM Events

| Function | Signature | Description |
| --- | --- | --- |
| `dispatchMarkommerceEvent` | `(target: EventTarget, name: K, detail: ..., options?: DispatchOptions): boolean` | Dispatch a `CustomEvent` on `target`. Returns `false` if the event was cancelled. |

### Vite Plugin

| Export | Description |
| --- | --- |
| `markommerceModuleScanner(options?)` | Vite plugin that scans `packages/` for `package.json` files with a `markommerce.extension` field and generates a barrel file at `outputPath` that side-effect-imports each module's extension entry point in priority order. |

**Plugin options:**

| Key | Default | Description |
| --- | --- | --- |
| `packagesPath` | `<repoRoot>/packages` | Directory to scan for module packages. |
| `outputPath` | `<repoRoot>/packages/frontend-demo/resources/js/.generated/extensions.ts` | Path to write the generated barrel file. |

### Latte Functions

| Function | Signature | Description |
| --- | --- | --- |
| `{vite()}` | `vite(?string $entry = null): Html` | Render `<script>` and `<link>` tags for the Vite entry point. Uses the configured default entry when `$entry` is `null`. Throws `ViteHelperException` if an empty string is passed. |

## Vite Plugin Setup

Add the `markommerceModuleScanner` plugin to the consumer's `vite.config.ts`. The plugin scans each package directory for a `package.json` with a `markommerce.extension` field and writes a generated barrel file that is imported by the entry point.

```typescript title="vite.config.ts"
import { defineConfig } from 'vite';
import markommerceModuleScanner from './build/vite-plugin-markommerce';

export default defineConfig({
  plugins: [
    markommerceModuleScanner({
      packagesPath: './packages',
      outputPath: './packages/frontend-demo/resources/js/.generated/extensions.ts',
    }),
  ],
  build: {
    manifest: true,
    rollupOptions: {
      input: './packages/frontend-demo/resources/js/main.ts',
    },
  },
});
```

Each package that wants to register components or hooks declares the extension entry in its `package.json`:

```json title="packages/my-module/package.json"
{
  "name": "@markommerce/my-module",
  "markommerce": {
    "extension": "./resources/js/index.ts",
    "priority": 10
  }
}
```

The kernel (`@markommerce/frontend`) always loads first regardless of priority.

## Latte Integration

### `{vite()}` Function

The `{vite()}` function renders the correct asset tags in Latte templates. In dev mode it points to the Vite dev server; in production it reads the manifest.

```latte
{* Render script and link tags for the default entry (config/vite.php → entry) *}
{vite()}

{* Render tags for a specific entry *}
{vite('resources/js/admin.ts')}
```

### MarkommerceLatteEngineFactory Preference Pattern

`MarkommerceLatteEngineFactory` extends `LatteEngineFactory` from `marko/view-latte` and registers `ViteExtension` with the Latte engine. It uses Marko's `#[Preference]` attribute so it automatically replaces the base factory without requiring manual wiring.

```php title="src/View/Latte/MarkommerceLatteEngineFactory.php"
use Latte\Engine;
use Marko\Core\Attributes\Preference;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\ViewConfig;
use Markommerce\Frontend\View\Latte\ViteExtension;

#[Preference(replaces: LatteEngineFactory::class)]
readonly class MarkommerceLatteEngineFactory extends LatteEngineFactory
{
    public function __construct(
        ViewConfig $viewConfig,
        private ViteExtension $viteExtension,
    ) {
        parent::__construct($viewConfig);
    }

    public function create(): Engine
    {
        $engine = parent::create();
        $engine->addExtension($this->viteExtension);

        return $engine;
    }
}
```

Any application that loads `markommerce/frontend` as a Marko module gets `{vite()}` in every Latte template automatically, without any extra configuration.

## Consumer-App Integration

### MARKOMMERCE_CONSUMER_PUBLIC

Markommerce itself has no web server entry point --- it runs inside a consuming application (such as the Marko playground). `marko/vite` resolves the manifest path as `<app-root>/public/<buildDirectory>/<manifestFilename>`, where `<app-root>` is the consuming app's working directory.

The Vite build must therefore write its output into the consuming app's `public/build/` directory. Set the `MARKOMMERCE_CONSUMER_PUBLIC` environment variable to the consuming app's `public/build/` path:

```bash
# Point Vite output at the consuming app (run from the markommerce repo root)
export MARKOMMERCE_CONSUMER_PUBLIC=../playground/public/build
npm run build
```

When `MARKOMMERCE_CONSUMER_PUBLIC` is unset (e.g., in CI or during in-repo tests), the build falls back to `<repoRoot>/public/build`.

### End-to-End Integration Steps

1. Add `markommerce/frontend` to the consuming app's `composer.json` (or as a path repository during local development).
2. Add `@markommerce/frontend` to the consuming app's `package.json` and run `npm install`.
3. Set `MARKOMMERCE_CONSUMER_PUBLIC` to point to the consuming app's `public/build/` directory.
4. Run `npm run dev` (dev server) or `npm run build` (production build) from the markommerce repo.
5. In any Latte template, call `{vite()}` to inject the asset tags.

## Related Packages

- [markommerce/frontend-demo](/docs/packages/frontend-demo/) --- demo storefront module that wires together the full frontend stack and demonstrates a working counter component.
- [marko/vite](https://github.com/marko-php/vite) --- the underlying `Vite` service and `marko/vite` Composer package that `MarkommerceLatteEngineFactory` delegates to for manifest reading and tag generation.
