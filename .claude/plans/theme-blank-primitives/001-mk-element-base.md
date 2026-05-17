# Task 001: Create `MkElement` Base Class

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add the shared `MkElement` base class to `@markommerce/frontend`. It extends `LitElement`, overrides `createRenderRoot()` to return `this` (light DOM), and overrides `render()` to return Lit's `nothing` token. This base class is the single source of truth for "what a Markommerce custom element looks like" — every Phase 2-5 component extends it. Returning `nothing` from the default render is the central CLS-prevention contract: subclasses opt in to active rendering by overriding.

## Context

- Files to create:
  - `packages/frontend/resources/js/MkElement.ts` — the class
  - `packages/frontend/resources/js/MkElement.test.ts` — Vitest unit test
- File to update:
  - `packages/frontend/resources/js/index.ts` — export `MkElement` from the kernel

- Reference patterns:
  - `packages/frontend-demo/resources/js/components/MarkommerceCounter.ts` shows the existing light-DOM + override pattern: `override createRenderRoot(): HTMLElement { return this; }`. `MkElement` codifies this for all consumers.
  - Lit's `nothing` token is exported from `lit` (top-level) and documented to mean "render nothing."

- `lit` is already a peer dep of `@markommerce/frontend` (check `packages/frontend/package.json`), so no new dependency is needed.

- **Critical contract** (paste into the source file's leading comment): The default `render()` returns Lit's `nothing` sentinel. `LitElement.update()` calls `render()` and writes the result into the render root via `lit-html` `render()`. Lit-element's `createRenderRoot()` override sets `renderOptions.renderBefore` to `renderRoot.firstChild` so the ChildPart is positioned *before* any existing light-DOM children. Returning `nothing` produces an empty ChildPart range, leaving the existing children intact. Verified in `node_modules/lit-element/development/lit-element.js` line 110 (`renderBefore ??= renderRoot.firstChild`) and `node_modules/lit-html/development/lit-html.js` line 1492 (the ChildPart's `endNode` becomes the first existing child, so cleared content only covers the empty range between the inserted Comment marker and that endNode).

- **TypeScript return-type contract**: Lit's base `render()` is typed as `protected render(): unknown`. `MkElement.render()` MUST return type-compatible-with-`unknown`, NOT `typeof nothing`. Declaring the base override as `override render(): typeof nothing` would prevent subclasses from returning `TemplateResult` (TypeScript does not allow widening a method's return type in a subclass override). The correct declaration is `override render(): unknown { return nothing; }` (or `TemplateResult | typeof nothing` if we want to surface the intent in the type, since `unknown` is a supertype of both). Subclasses then override with their own narrower return type (e.g. `override render(): TemplateResult`).

## Requirements (Test Descriptions)
- [x] `it exports MkElement from @markommerce/frontend`
- [x] `MkElement extends LitElement`
- [x] `MkElement createRenderRoot returns the element itself (light DOM)`
- [x] `MkElement default render returns the nothing sentinel`
- [x] `defining a subclass tag and appending an instance to the DOM with existing light-DOM children does not remove those children after Lit's first update (await updateComplete then assert children preserved)`
- [x] `subclasses that override render() can return a TemplateResult without TypeScript override errors and the rendered template is inserted before the existing children, not in place of them`

## Acceptance Criteria
- All requirements have passing tests
- TypeScript types are correct: the base `render()` is declared `override render(): unknown { return nothing; }` (matching `LitElement.render(): unknown`). Subclasses can override with `override render(): TemplateResult` without TS errors. The base must NOT narrow the return type to `typeof nothing` — that would block subclasses from overriding with a `TemplateResult`.
- `npm test` passes
- No `final` keyword in the TS source (subclasses must extend it freely)
- The class is `export class MkElement extends LitElement` — exported as a named export, not default
- Test pattern for "append + assert children preserved": use a unique throwaway tag like `mk-element-test-1`, define a subclass via `class extends MkElement {}`, register with `customElements.define`, then append `<mk-element-test-1><span>preserved</span></mk-element-test-1>`, await the element's `updateComplete`, then assert `el.querySelector('span')` still resolves and `el.textContent` still contains `preserved`. A Lit `<!---->` Comment marker WILL be inserted as the new first child of the host (this is expected and CLS-safe — Comments have no layout impact); the assertion must therefore key off element-querying, not `firstChild` identity.

## Implementation Notes
- `MkElement.createRenderRoot()` returns `this` (light DOM) and sets `this.renderOptions.renderBefore ??= this.firstChild` so that Lit's lit-html render positions the ChildPart marker *before* existing light-DOM children. Without this, `renderBefore` would be `null` (no super call), causing rendered content to appear after existing children.
- `render()` is declared `override render(): unknown` to match `LitElement.render(): unknown`, allowing subclasses to override with `TemplateResult` without TypeScript errors.
- `packages/frontend/resources/js/index.ts` exports `MkElement` as a named export.
