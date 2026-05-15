# markommerce/frontend

Frontend integration for Markommerce --- Latte helpers, Vite asset wiring, and JS/CSS component plumbing.

## Installation

```bash
composer require markommerce/frontend
```

```bash
npm install @markommerce/frontend
```

## Quick Example

```typescript
import { registerBase, addMixin, defineAllComponents } from '@markommerce/frontend';

registerBase('my-counter', class extends HTMLElement {});

addMixin('my-counter', (Base) => class extends Base {
  connectedCallback() { this.textContent = '0'; }
}, { source: 'my-module', priority: 10 });

defineAllComponents();
```

## Documentation

Full usage, API reference, and examples: [markommerce/frontend](https://markommerce.dev/docs/packages/frontend/)
