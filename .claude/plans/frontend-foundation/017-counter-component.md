# Task 017: Implement MarkommerceCounterElement Lit component

**Status**: complete
**Depends on**: 007, 010, 016
**Retry count**: 0

## Description

Implement the `<markommerce-counter>` Web Component as the Phase 1 proof-of-life. Component lives in `packages/frontend-demo/resources/js/components/MarkommerceCounter.ts`. It renders to Light DOM, uses the cascade-layered semantic tokens for its styling, exposes `protected` template methods (`renderLabel`, `renderButton`, `renderExtras`) so mixins can extend it, dispatches a `markommerce:counter:changed` CustomEvent on increment, and has a `start-value` HTML attribute. Author a `LabelSuffixMixin` in `packages/frontend-demo/resources/js/mixins/LabelSuffixMixin.ts` that appends a configurable suffix to the rendered label — this is the canonical demonstration of the mixin pattern. Register both in the demo's `index.ts`.

## Context

- Component file: `packages/frontend-demo/resources/js/components/MarkommerceCounter.ts`.
- Mixin file: `packages/frontend-demo/resources/js/mixins/LabelSuffixMixin.ts`.
- Declaration-merge `MarkommerceEventMap` to include `'markommerce:counter:changed': { count: number }`.
- The component extends `LitElement` and overrides `createRenderRoot()` to return `this` (Light DOM).
- Styles live in `packages/frontend-demo/resources/css/components/counter.css` (imported by main.ts via `import '../css/components/counter.css'`). They reference Markommerce semantic tokens (`var(--color-primary)`, `var(--space-2)`, etc.) so they inherit from the cascade.
- The component has:
  - `@property({type: Number, attribute: 'start-value'}) startValue = 0`
  - `@state() protected count = 0`
  - `connectedCallback` sets `count = startValue`
  - `protected increment()` increments and dispatches the typed CustomEvent
  - `protected renderLabel()` returns the textual label
  - `protected renderButton()` returns the `<button>`
  - `protected renderExtras()` returns empty html
  - `render()` composes the three template methods
- `LabelSuffixMixin` is a function that takes the base class and returns a subclass with a `suffix` property; it overrides `renderLabel` to append the suffix.
- The demo's `index.ts` runs:
  ```ts
  import { registerBase, addMixin } from '@markommerce/frontend';
  import { MarkommerceCounterElement } from './components/MarkommerceCounter';
  import { LabelSuffixMixin } from './mixins/LabelSuffixMixin';
  registerBase('markommerce-counter', MarkommerceCounterElement);
  addMixin('markommerce-counter', LabelSuffixMixin, { source: '@markommerce/frontend-demo', priority: 100 });
  ```

## Requirements (Test Descriptions)

- [x] `it renders a button with the initial count of 0 by default`
- [x] `it accepts start-value as an HTML attribute and uses it as the initial count`
- [x] `it increments the count by one on button click`
- [x] `it dispatches a markommerce:counter:changed event with the new count in detail on increment`
- [x] `it renders to light DOM so global theme styles apply`
- [x] `it exposes a renderLabel template method that mixins can override`
- [x] `it exposes a renderButton template method that mixins can override`
- [x] `it exposes a renderExtras template method that mixins can extend`
- [x] `it composes a LabelSuffixMixin so the label displays count plus suffix when the mixin is registered`
- [x] `the LabelSuffixMixin defaults its suffix to a documented placeholder when no attribute is set`
- [x] `it imports the counter.css stylesheet so its styles register under the components cascade layer`
- [x] `the dispatched event has bubbles true and composed true`
- [x] `the typed event map includes markommerce:counter:changed via declaration merging`

## Acceptance Criteria

- Vitest + happy-dom tests pass.
- TypeScript: zero `any`, zero `@ts-ignore` / `@ts-expect-error`. **Important:** because the counter renders to Light DOM (`createRenderRoot()` returns `this`), Lit's `static styles` block is irrelevant — it only applies to Shadow DOM. All component styling comes from the imported `counter.css` file (which the bundle pulls in via `import '../css/components/counter.css'` from `main.ts`). The original concern about `@ts-ignore` for `static styles` accumulation across mixins does NOT apply to this Light-DOM component; if a future Shadow-DOM component needs that pattern it will document the workaround at that time.
- The component's CSS uses only Markommerce semantic tokens (no hex colours, no raw px values for spacing).
- Manual smoke test: `npm run dev`, navigate to the demo route (task 018), observe counter increments and console-logged event.

## Implementation Notes

- `render()` wraps composed templates in `<div class="counter">` because happy-dom's template parser mishandles Lit ChildPart markers at the root level of a template (adjacent comment nodes at root get corrupted). Wrapping in a `<div>` is semantically correct for a counter component and resolves this limitation.
- `renderExtras()` returns `nothing` (Lit's sentinel for "render nothing") rather than `html\`\`` because the empty template also triggers the same happy-dom root-level part issue. `nothing` is idiomatic Lit for "no content" and renders as an empty ChildPart in production.
- The `LabelSuffixMixin` accesses `this.count` (a `protected` reactive state) which TypeScript allows since the mixin subclasses the base.
- Declaration merging on `@markommerce/frontend`'s `MarkommerceEventMap` is done in `MarkommerceCounter.ts` via `declare module '@markommerce/frontend'`.
- CSS import path from `components/MarkommerceCounter.ts` is `../../css/components/counter.css` (up two levels to `resources/`, then down to `css/`).
