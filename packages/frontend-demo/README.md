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

## Documentation

Full usage, API reference, and examples: [markommerce/frontend-demo](https://markommerce.dev/docs/packages/frontend-demo/)
