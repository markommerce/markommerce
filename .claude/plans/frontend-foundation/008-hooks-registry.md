# Task 008: Implement hooks registry

**Status**: completed
**Depends on**: 004, 005
**Retry count**: 0

## Description

Implement the data-transformation hooks system at `packages/frontend/resources/js/hooks.ts`. The system stores ordered handler arrays keyed by hook name and runs them in priority order, threading payload through each handler (synchronous or async). The `HookRegistry` interface starts empty (no concrete hooks for the kernel) so consumers can extend it via TypeScript declaration merging. Exports `registerHook`, `runHook`, and a convenience `Hooks` namespace. Re-export from `index.ts`.

## Context

- File: `packages/frontend/resources/js/hooks.ts`.
- The kernel ships zero hooks. Concrete hook names belong to domain modules (cart, catalog, etc.) that arrive in later phases.
- `runHook` must handle async handlers — `Promise.resolve(handler(...))` chained so a sync handler in a list of async handlers does the right thing.
- Strong typing via `keyof HookRegistry` and indexed access types for `payload` / `context` / `return`.
- Public API contract (consumers extend via declaration merging):
  ```ts
  // Empty in the kernel. Modules merge new keys via:
  //   declare module '@markommerce/frontend' {
  //     interface HookRegistry {
  //       'cart:before-add': { payload: { sku: string; qty: number }; return: { sku: string; qty: number } };
  //     }
  //   }
  export interface HookRegistry {}

  export type HookHandler<K extends keyof HookRegistry> = (
    payload: HookRegistry[K] extends { payload: infer P } ? P : never,
  ) =>
    | (HookRegistry[K] extends { return: infer R } ? R : never)
    | Promise<HookRegistry[K] extends { return: infer R } ? R : never>;

  export function registerHook<K extends keyof HookRegistry>(
    name: K,
    handler: HookHandler<K>,
    options?: { priority?: number },   // default 100; lower runs first; stable sort
  ): void;

  export function runHook<K extends keyof HookRegistry>(
    name: K,
    payload: HookRegistry[K] extends { payload: infer P } ? P : never,
  ): Promise<HookRegistry[K] extends { return: infer R } ? R : never>;

  export const Hooks: { register: typeof registerHook; run: typeof runHook };
  ```

## Requirements (Test Descriptions)

- [ ] `it registers a hook handler and runs it via runHook`
- [ ] `it threads the payload through multiple handlers in priority order`
- [ ] `it returns the unmodified payload when no handlers are registered for the hook name`
- [ ] `it defaults handler priority to 100 when not specified`
- [ ] `it sorts handlers stably so equal priorities keep registration order`
- [ ] `it awaits async handlers and chains their resolved values to the next handler`
- [ ] `it allows a sync handler in a chain of async handlers without breaking the threading`
- [ ] `it surfaces handler exceptions via the returned Promise rejection`
- [ ] `it exposes the Hooks namespace with register and run aliases`

## Acceptance Criteria

- TypeScript: `HookRegistry` is a generic interface that consumers can declaration-merge into; the kernel exports no concrete keys.
- Tests cover both sync and async chains.
- Re-exported from `index.ts`.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
