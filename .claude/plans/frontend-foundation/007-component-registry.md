# Task 007: Implement component registry

**Status**: complete
**Depends on**: 004, 005
**Retry count**: 0

## Description

Implement the kernel's component registry at `packages/frontend/resources/js/registry.ts`. It records base classes and mixins keyed by Custom Element tag name and composes the final element class when `defineAllComponents()` is called. Throws explicit errors for double-registration and dangling mixins (mixin added before base). Exports introspection helpers for development logging. Tested via Vitest + happy-dom. Re-export the public API from the package's `index.ts`.

## Context

- File: `packages/frontend/resources/js/registry.ts`.
- Public API (no exotic additions, the contract is exhaustive):
  ```ts
  export type Constructor<T> = new (...args: never[]) => T;
  export type Mixin<TBase extends Constructor<HTMLElement>> = (Base: TBase) => TBase;

  export interface MixinDescriptor<TBase extends Constructor<HTMLElement>> {
    mixin: Mixin<TBase>;
    source: string;       // e.g. '@markommerce/frontend-demo' — for getMixinChain introspection
    priority?: number;    // default 100; lower applies first (innermost wrapper)
  }

  export interface RegisteredComponent {
    tagName: string;
    base: Constructor<HTMLElement>;
    mixins: ReadonlyArray<MixinDescriptor<Constructor<HTMLElement>>>;
  }

  export function registerBase(tagName: string, BaseClass: Constructor<HTMLElement>): void;
  export function addMixin<TBase extends Constructor<HTMLElement>>(
    tagName: string,
    mixin: Mixin<TBase>,
    options: { source: string; priority?: number },
  ): void;
  export function defineAllComponents(): void;        // idempotent
  export function getRegisteredComponents(): readonly RegisteredComponent[];
  export function getMixinChain(tagName: string): readonly { source: string; priority: number }[];

  export class RegistryError extends Error {}
  ```
- Mixin priority: lower number applies first (innermost wrapper); the kernel itself is priority 0 (base only — no mixin).
- Errors are plain `Error` subclasses (`RegistryError`) so callers can catch them without depending on a Node-only error system.
- Tests live at `packages/frontend/resources/js/registry.test.ts` (co-located with source — Vitest default).

## Requirements (Test Descriptions)

- [x] `it registers a base class with registerBase and exposes it via getRegisteredComponents`
- [x] `it throws RegistryError when registerBase is called twice for the same tag name`
- [x] `it adds a mixin via addMixin and lists it via getMixinChain`
- [x] `it throws RegistryError when addMixin is called for a tag with no registered base`
- [x] `it applies a single mixin to a base class on defineAllComponents and the element registers in customElements`
- [x] `it applies multiple mixins in ascending priority order producing a deterministic composition`
- [x] `it defaults mixin priority to 100 when not specified`
- [x] `it records the source string of each mixin for getMixinChain output`
- [x] `it composes mixins so a later mixin can call super to chain template methods`
- [x] `it makes defineAllComponents idempotent — calling twice does not re-define elements`
- [x] `it leaves customElements untouched until defineAllComponents is called`

## Acceptance Criteria

- All tests pass with happy-dom.
- TypeScript types: `registerBase`, `addMixin` are generic over `Constructor<LitElement>`; no `any` leakage in public signatures.
- Re-exported from `packages/frontend/resources/js/index.ts`.
- 100% coverage on `registry.ts` (small file — fully exercisable).

## Implementation Notes

- Created `packages/frontend/resources/js/registry.ts` with the full public API.
- Module-level `Map` and `defined` flag hold state; `_resetForTesting()` clears both between tests.
- `addMixin` casts the typed `Mixin<TBase>` to `Mixin<Constructor<HTMLElement>>` internally to keep the stored array homogeneous.
- `defineAllComponents` sorts mixins by `priority ?? 100` ascending and folds them with `reduce`-style loop; calls `customElements.define` once per tag.
- `getMixinChain` returns a sorted snapshot with `priority` normalised to 100 when absent.
- Re-exported from `packages/frontend/resources/js/index.ts` (added before existing events/hooks exports).
- postcss-import and related packages were missing in the Docker node_modules; installed them to unblock Vitest CSS processing.
- All 81 Vitest tests (9 files) pass.
