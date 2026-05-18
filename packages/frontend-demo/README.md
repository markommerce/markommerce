# markommerce/frontend-demo

Reference and smoke-test module for the Markommerce frontend stack --- demonstrates a working counter component with a mixin extension and typed DOM events.

## Installation

This package is a development-only dependency. Install it via `require-dev`:

```bash
composer require-dev markommerce/frontend-demo
```

Install the npm package:

```bash
npm install @markommerce/frontend-demo
```

## Quick Example

```typescript
import { registerBase, addMixin } from '@markommerce/frontend';
import { MarkommerceCounterElement } from '@markommerce/frontend-demo';
import { LabelSuffixMixin } from '@markommerce/frontend-demo';

registerBase('markommerce-counter', MarkommerceCounterElement);
addMixin('markommerce-counter', LabelSuffixMixin, { source: '@markommerce/frontend-demo', priority: 100 });
```

## Related demo

The `mk-*` primitives and form controls now live in [`markommerce/theme-blank-demo`](https://markommerce.dev/docs/packages/theme-blank-demo/), rendered at `/markommerce/_demo/theme-blank`. This package (`frontend-demo`) keeps its original role: a smoke test for the `@markommerce/frontend` kernel (custom-element registry, mixin chain, ViteExtension wiring).

## Documentation

Full usage, API reference, and examples: [markommerce/frontend-demo](https://markommerce.dev/docs/packages/frontend-demo/)
