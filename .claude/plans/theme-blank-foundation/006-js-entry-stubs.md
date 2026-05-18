# Task 006: JS Entry with `showToast` / `openModal` Stubs

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the theme-blank JS entry point at `packages/theme-blank/resources/js/index.ts` exporting two stub controllers (`showToast`, `openModal`) plus their option types (`ToastOptions`, `ModalOptions`). The real behavior arrives in Phase 4; Phase 1 locks the public surface so downstream code can target it now and Phase 4's expansion is non-breaking. Each stub emits a `console.warn` and returns a documented stub value.

## Context
- File to create: `packages/theme-blank/resources/js/index.ts`
- The package.json (task 001) already declares `markommerce.extension` pointing at this file with priority 100, so once it exists, the Vite scanner plugin picks it up automatically.
- **API shape** — these signatures *must not change* in Phase 4:
  ```ts
  export type ToastVariant = 'info' | 'success' | 'warning' | 'danger';

  export interface ToastOptions {
    variant?: ToastVariant;
    duration?: number;
  }

  export type ModalSize = 'sm' | 'md' | 'lg';

  export interface ModalOptions {
    dismissible?: boolean;
    size?: ModalSize;
  }

  export interface ModalHandle {
    close(): void;
  }

  export function showToast(message: string, options?: ToastOptions): void;
  export function openModal(content: HTMLElement | string, options?: ModalOptions): ModalHandle;
  ```
- **Stub behavior:**
  - `showToast(message, options)` calls `console.warn('[markommerce/theme-blank] showToast() stub — real implementation lands in Phase 4', { message, options })` and returns `undefined`.
  - `openModal(content, options)` calls `console.warn('[markommerce/theme-blank] openModal() stub — real implementation lands in Phase 4', { content, options })` and returns `{ close: () => {} }`.
- **Vitest tests** live alongside source at `packages/theme-blank/resources/js/index.test.ts`. Use the existing `happy-dom` env and spy on `console.warn` via Vitest's `vi.spyOn`.
- **Side-effect imports.** The entry should *not* call `defineAllComponents()` from `@markommerce/frontend` — that's `frontend-demo`'s job at the end of the chain. theme-blank only registers its (currently empty) extensions and exports the toast/modal API. In Phase 2+ we'll add side-effect imports here as components arrive.
- Update `frontend-demo`'s `.generated/extensions.ts` will pick up theme-blank automatically via the scanner plugin; no manual wire-up.

## Requirements (Test Descriptions)
- [ ] `it exports a showToast function with signature (message: string, options?: ToastOptions) => void`
- [ ] `it exports an openModal function with signature (content: HTMLElement | string, options?: ModalOptions) => ModalHandle`
- [ ] `it exports ToastOptions with optional variant ('info' | 'success' | 'warning' | 'danger') and duration (number) fields`
- [ ] `it exports ModalOptions with optional dismissible (boolean) and size ('sm' | 'md' | 'lg') fields`
- [ ] `it exports ModalHandle with a close() method`
- [ ] `showToast() emits a console.warn marking itself as a Phase 1 stub`
- [ ] `showToast() returns undefined`
- [ ] `openModal() emits a console.warn marking itself as a Phase 1 stub`
- [ ] `openModal() returns an object with a callable close method that returns void`
- [ ] `the entry file does not call defineAllComponents()` (assert by reading source)
- [ ] `the theme-blank package.json declares markommerce.extension pointing at ./resources/js/index.ts with priority 100` (regression guard from task 001)

## Acceptance Criteria
- All requirements have passing tests
- `npm run test` passes
- `npm run typecheck` passes
- `npm run lint:js` passes
- The Vite scanner plugin output (`packages/frontend-demo/resources/js/.generated/extensions.ts`) includes a reference to `@markommerce/theme-blank` after running `npm run build` (manual verification — no automated assertion needed)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
