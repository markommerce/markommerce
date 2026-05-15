# Task 009: Implement DOM events helper + MarkommerceEventMap

**Status**: completed
**Depends on**: 004, 005
**Retry count**: 0

## Description

Provide a thin typed-event helper at `packages/frontend/resources/js/events.ts` for components to dispatch CustomEvents with `bubbles: true, composed: true` defaults. Declare a `MarkommerceEventMap` interface (empty in the kernel) and extend the global `DocumentEventMap` and `HTMLElementEventMap` via declaration merging so listeners get autocompletion. Export `dispatchMarkommerceEvent(target, name, detail)` to make the common idiom one line.

## Context

- File: `packages/frontend/resources/js/events.ts`.
- The kernel declares no events. Module authors extend the map via `declare module '@markommerce/frontend' { interface MarkommerceEventMap { ... } }`.
- For Phase 1's demo we still need at least one event — `markommerce:counter:changed` — but that lives in the demo package's types, not the kernel. The kernel only provides the mechanism.
- The helper sets sane defaults but allows overrides for `bubbles` / `composed` / `cancelable`.
- Public API contract:
  ```ts
  // Empty in the kernel. Modules merge new keys via declaration merging.
  export interface MarkommerceEventMap {}

  export interface DispatchOptions {
    bubbles?: boolean;       // default true
    composed?: boolean;      // default true
    cancelable?: boolean;    // default false
  }

  export function dispatchMarkommerceEvent<K extends keyof MarkommerceEventMap & string>(
    target: EventTarget,
    name: K,
    detail: MarkommerceEventMap[K],
    options?: DispatchOptions,
  ): boolean;

  // Augment global DOM event maps so document.addEventListener gets autocompletion:
  declare global {
    interface DocumentEventMap extends MarkommerceEventMap {}
    interface HTMLElementEventMap extends MarkommerceEventMap {}
  }
  ```

## Requirements (Test Descriptions)

- [ ] `it dispatches a CustomEvent with bubbles true and composed true by default`
- [ ] `it includes the supplied detail object verbatim on the event`
- [ ] `it returns the dispatchEvent boolean result for callers that need it`
- [ ] `it allows overriding bubbles, composed, and cancelable via options`
- [ ] `it dispatches from the supplied target element so listeners on ancestors fire`
- [ ] `it provides a MarkommerceEventMap interface that consumers can declaration-merge into`
- [ ] `it augments DocumentEventMap and HTMLElementEventMap via declaration merging in the kernel index types`

## Acceptance Criteria

- TypeScript autocompletion for `document.addEventListener('markommerce:...')` works once a consumer extends the map (verified by a fixture test that declares a custom event in a `.test.ts` file).
- Helper has no runtime dependencies beyond DOM globals.
- Re-exported from `index.ts`.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
