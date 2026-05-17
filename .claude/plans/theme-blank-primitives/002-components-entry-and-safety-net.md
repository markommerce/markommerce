# Task 002: Components Entry + `:not(:defined)` Safety Net

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the side-effect module that registers all 12 Phase 2 primitives, wire it into the package's Vite entry, extend `base.css` with the `:not(:defined)` safety-net rule covering all 12 tag names, and verify stylelint accepts the rule. This task lays the *empty hooks* for tasks 003-014 — each component task drops a single line into `components/index.ts` and a CSS import into its own `.ts` file, with no editing of shared files. The safety-net rule applies the same `display` declaration that the upgraded element uses, so pre- and post-upgrade layouts are identical (the CLS invariant).

## Context

- Files to create:
  - `packages/theme-blank/resources/js/components/index.ts` — side-effect file with import lines for all 12 components (commented out until each task fills them in OR, preferred, all 12 import lines present and each refers to a stub module that this task scaffolds; see Implementation Strategy below)
  - `packages/theme-blank/resources/js/components/index.test.ts` — asserts the module is importable and registers all 12 tags (the actual component files are scaffolded as stubs in this task)
  - Stub source files for all 12 components: `packages/theme-blank/resources/js/components/mk-{name}.ts` — each exporting only `import { registerBase } from '@markommerce/frontend'; import { MkElement } from '@markommerce/frontend'; export class Mk{Name}Element extends MkElement {} registerBase('mk-{name}', Mk{Name}Element);` AND a sibling empty CSS file `packages/theme-blank/resources/css/components/mk-{name}.css` containing only `@layer components {}`
- Files to update:
  - `packages/theme-blank/resources/js/index.ts` — add `import './components';` as the first line so importing `@markommerce/theme-blank` registers all components as a side effect
  - `packages/theme-blank/resources/css/base.css` — add the safety-net rule
  - `packages/theme-blank/package.json` — if needed, expose `./css/components/*` paths from `exports` so consumers can import individual component CSS files (verify the current `exports` block; the wildcard pattern may already cover this)
  - `stylelint.config.js` — verify `@container` is allowed; add if missing (this is a forward-looking allowance even though Phase 2 tasks 007/008 are what actually use it)

- Reference: `packages/frontend-demo/resources/js/index.ts` is the exemplar for the registration pattern (`registerBase(tag, Class)`).

- **Stub strategy:** This task scaffolds *all 12 components as empty stubs* so that tasks 003-014 do not need to touch shared files. Each stub:
  - Class is `Mk{Name}Element extends MkElement` with no overrides
  - Module imports its matching CSS file via `import '../../css/components/mk-{name}.css';` so cascade-layer wiring is established
  - Calls `registerBase('mk-{name}', Mk{Name}Element)` at module top level
  - The CSS file contains `@layer components { /* placeholder — filled in by task 00X */ }` (the comment inside satisfies stylelint's `block-no-empty` rule; identical pattern for the `:not(:defined)` rule in base.css)
  - Tasks 003-014 each replace the stub's CSS body and (optionally) add reflected attributes to the class

- **Safety-net rule — corrected understanding:**

  The actual mechanism that prevents pre-upgrade CLS in Phase 2 is **NOT** the `:not(:defined)` rule. It is the fact that every component's `@layer components` CSS uses a plain tag selector (`mk-stack { display: flex; ... }`). CSS does not require an element to be a *defined* custom element to match a tag-name selector — the browser parses `<mk-stack>` as an `HTMLUnknownElement` and CSS rules with that tag name apply immediately. Therefore the layout is correct from first paint, regardless of whether `customElements.define()` has run yet.

  The UA default for custom elements / `HTMLUnknownElement` is `display: inline`, NOT `display: block`. A `display: revert` safety-net rule would explicitly revert to `inline`, which would be WRONG for `mk-stack`, `mk-grid`, `mk-cover`, etc. (they need `flex`/`grid`/`block`). But this never matters because `@layer components` always wins over `@layer base` regardless of selector specificity.

  The `:not(:defined)` rule is therefore retained only as a **belt-and-braces visibility hint** to communicate that the unupgraded state is intentional. The rule shape we ship:

  ```css
  @layer base {
    /* CLS safety net documentation hook: every Phase 2 primitive renders correctly
     * from its @layer components tag selector even before the JS class is defined.
     * This rule exists so consumers can see at a glance which tags participate in
     * the contract, and to provide a downstream override point. The :not(:defined)
     * matcher is intentionally a no-op for layout (components layer wins anyway). */
    mk-stack:not(:defined),
    mk-cluster:not(:defined),
    mk-grid:not(:defined),
    mk-container:not(:defined),
    mk-sidebar:not(:defined),
    mk-switcher:not(:defined),
    mk-cover:not(:defined),
    mk-divider:not(:defined),
    mk-heading:not(:defined),
    mk-text:not(:defined),
    mk-link:not(:defined),
    mk-badge:not(:defined) {
      /* No declarations — the @layer components tag selectors supply the real layout.
       * This selector group is retained as a documentation hook and downstream override
       * point. The trailing comment also satisfies stylelint's block-no-empty rule. */
    }
  }
  ```

  Acceptance for this part is verified by task 015's *unupgraded* Playwright test: the fixture does not load any JS, so every element matches `:not(:defined)`, yet the @layer components rules produce the correct (CLS-zero) layout.

- **Vite + scanner wiring:** `frontend-demo/resources/js/main.ts` line 11 imports `./.generated/extensions`, which the Vite scanner populates from each package's `markommerce.extension` declaration in `package.json`. The theme-blank `package.json` already has `markommerce.extension: ./resources/js/index.ts`. Adding `import './components';` to `theme-blank/resources/js/index.ts` is sufficient to wire everything up — no Vite-config edits needed. The generated file (`packages/frontend-demo/resources/js/.generated/extensions.ts`) regenerates on every `vite build` / `vite dev` run via the plugin's `buildStart` hook; it emits a single `import` statement per scanned package, so Vite's side-effect resolution will pull `components/index.ts` transitively.

- **Test isolation for stub components:** Each per-component test file (tasks 003-014) will import its `./mk-*.ts` stub, which calls `registerBase()` at module top level. Vitest isolates ES module graphs per test file, so cross-file double-registration is not a concern. Within a single test file, ESM module caching prevents re-execution of `registerBase`. However, the registry module is also imported transitively in any test that uses `getRegisteredComponents()` — the registry is module-scoped and shared across imports. If a test calls `defineAllComponents()`, the global `customElements` registry receives the definition; subsequent `customElements.define()` calls for the same tag in that test file will throw. The plan for tasks 003-014 should therefore prescribe: (a) import the stub module to trigger registration, (b) call `defineAllComponents()` once via beforeAll (or skip if already defined via `customElements.get()` guard), (c) instantiate via `document.createElement(tag)` rather than `new Class()`. Update the test descriptions in tasks 003-014 accordingly if they prescribe direct construction.

- **Tag name → class name convention:**
  - `mk-stack` → `MkStackElement`
  - `mk-cluster` → `MkClusterElement`
  - `mk-grid` → `MkGridElement`
  - `mk-container` → `MkContainerElement`
  - `mk-sidebar` → `MkSidebarElement`
  - `mk-switcher` → `MkSwitcherElement`
  - `mk-cover` → `MkCoverElement`
  - `mk-divider` → `MkDividerElement`
  - `mk-heading` → `MkHeadingElement`
  - `mk-text` → `MkTextElement`
  - `mk-link` → `MkLinkElement`
  - `mk-badge` → `MkBadgeElement`

## Requirements (Test Descriptions)
- [ ] `it ships packages/theme-blank/resources/js/components/index.ts that imports all 12 primitive modules`
- [ ] `it scaffolds 12 stub component .ts files, each importing its matching .css file and calling registerBase with the correct tag name`
- [ ] `it scaffolds 12 empty .css files under packages/theme-blank/resources/css/components/, each containing only the @layer components block`
- [ ] `importing @markommerce/theme-blank registers all 12 primitive tags via the mixin registry (assert with getRegisteredComponents() from @markommerce/frontend)`
- [ ] `each registered class extends MkElement (assert via instanceof MkElement after calling defineAllComponents())`
- [ ] `packages/theme-blank/resources/css/base.css contains a :not(:defined) selector group listing every Phase 2 tag name`
- [ ] `the safety-net rule lives inside the @layer base block`
- [ ] `stylelint passes on the updated base.css (an empty declaration block under a grouped :not(:defined) selector is allowed — disable block-no-empty for that selector if stylelint-config-standard objects, OR include a no-op declaration like /* see @layer components */ comment plus a benign decl)`
- [ ] `stylelint passes on every new components/mk-*.css file (each containing only an @layer components {} block during the stub phase — disable no-empty-source for stubs OR include a /* stub */ comment)`
- [ ] `stylelint.config.js accepts @container at-rules (verified by adding a fixture .css file under packages/theme-blank/resources/css/components/ containing @container and running ./node_modules/.bin/stylelint on it; if it fails, add 'container' to ignoreAtRules in stylelint.config.js)`
- [ ] `theme-blank/package.json exports block resolves @markommerce/theme-blank/css/components/mk-stack.css to the new file (proxy test for the wildcard pattern; if the existing exports block has no wildcard, add "./css/components/*.css": "./resources/css/components/*.css")`

## Acceptance Criteria
- All 12 stub `.ts` files exist with `extends MkElement` and `registerBase()` calls
- All 12 stub `.css` files exist with the `@layer components {}` block (and nothing else)
- `npm test` passes
- `npm run test:cls` still passes (existing Phase 1 fixture is untouched)
- Stylelint passes
- No `defineAllComponents()` call lives in `theme-blank` itself — that stays in `frontend-demo/main.ts` (the application is responsible for definition timing)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
