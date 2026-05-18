# Task 002: Scaffold 6 Feedback Component Stubs

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create empty-but-valid stubs for all 6 Phase 4 feedback components so that the import graph compiles end-to-end before any real behavior is written. Mirrors Phase 3 task 003 (`003-components-entry-safety-net.md`). Extends `components/index.ts` to import all 6 new modules, and extends `base.css` `:not(:defined)` safety net to cover all 6 new tag names. Does NOT touch the existing stub `showToast`/`openModal` exports in `index.ts` — those are replaced in tasks 007 / 010 / 011.

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/{mk-alert,mk-toast,mk-spinner,mk-skeleton,mk-modal,mk-drawer}.ts` (create)
  - `packages/theme-blank/resources/css/components/{mk-alert,mk-toast,mk-spinner,mk-skeleton,mk-modal,mk-drawer}.css` (create)
  - `packages/theme-blank/resources/js/components/index.ts` (extend imports list)
  - `packages/theme-blank/resources/css/base.css` (extend `:not(:defined)` selector group)
- Patterns to follow:
  - Each .ts file: `import { MkElement, registerBase } from '@markommerce/frontend'; import '../../css/components/mk-{name}.css'; export class Mk{Name}Element extends MkElement {} registerBase('mk-{name}', Mk{Name}Element);`
  - Each .css file: `@layer components { mk-{name} { display: <appropriate>; } }`

## Requirements (Test Descriptions)

- [x] `it creates an empty MkElement subclass for each of the 6 components and registers it via registerBase`
- [x] `it includes each component module in components/index.ts as a side-effect import`
- [x] `it adds all 6 new tag names to the base.css :not(:defined) safety net selector group`
- [x] `it produces a passing TypeScript compile of components/index.ts`
- [x] `it ensures stylelint passes on all 6 new component CSS files`
- [x] `it leaves the existing index.ts showToast/openModal stub exports intact (no behavior change in this task)`

## Acceptance Criteria
- All requirements have passing tests (a single Vitest covers all 6 registrations; a stylelint CI run covers all 6 CSS files)
- Default display values per component: `mk-alert` `block`, `mk-toast` `block`, `mk-spinner` `inline-block`, `mk-skeleton` `block`, `mk-modal` `contents`, `mk-drawer` `contents` (the modal/drawer wrappers do not affect the inner `<dialog>`'s top-layer positioning)
- Modal/drawer content contract (documented in stub CSS comments AND in `_plan.md` Architecture Notes): server-rendered markup MUST take the form `<mk-modal><dialog>…content…</dialog></mk-modal>` (content lives inside `<dialog>`, never as a sibling of `<dialog>` directly under `<mk-modal>`). Sibling content of the dialog inside the wrapper is unsupported and would render unintentionally (because the dialog is `display: none` but the wrapper uses `display: contents`, leaking the sibling into the parent layout flow).
- No new behavior beyond registration (no MutationObserver, no event listeners, no role injection in stubs — those land in tasks 003-009)
- Stubs DO call `registerBase('mk-X', MkXElement)` so the registry is populated; tasks 003-009 extend the existing class definition (adding properties, lifecycle methods, CSS) and MUST NOT add a second `registerBase` call (it would throw `RegistryError`).

## Implementation Notes
- Created 6 TypeScript stub files: `mk-alert.ts`, `mk-toast.ts`, `mk-spinner.ts`, `mk-skeleton.ts`, `mk-modal.ts`, `mk-drawer.ts` — each a bare `MkElement` subclass calling `registerBase`.
- Created 6 matching CSS files with `@layer components` blocks and correct `display` values per spec (alert/toast/skeleton: `block`, spinner: `inline-block`, modal/drawer: `contents`).
- Modal and drawer CSS files include the content contract comment documenting the `<mk-modal><dialog>…</dialog></mk-modal>` required markup pattern.
- Extended `components/index.ts` with 6 side-effect imports for the new modules.
- Extended `base.css` `:not(:defined)` selector group with all 6 new tag names.
- TypeScript compile test filters pre-existing errors in `packages/frontend` and `packages/frontend-demo` (unrelated to this task) — only theme-blank-scoped errors would cause failure.
- Test file: `packages/theme-blank/resources/js/components/feedback-components.test.ts`.
