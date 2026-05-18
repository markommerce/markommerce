# Task 003: Scaffold Stubs + Extend Components Entry + `:not(:defined)` Safety Net

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Scaffold empty stub files for all 10 new form components (10 `.ts` + 10 `.css`), extend the existing `components/index.ts` side-effect entry to import all 10 new form component modules, and extend `base.css` to include the 10 new custom-element tag names in the `:not(:defined)` safety-net selector group.

**Why stubs first**: Tasks 004-013 depend on task 003 (each component imports `requireInnerControl` from `@markommerce/frontend` which 002 ships, plus the entry-point wiring 003 produces). Task 003 imports their files. Without stubs, 003 cannot pass TypeScript compilation while 004-013 are still pending. The Phase 2 pattern (see `packages/theme-blank/resources/js/components/index.test.ts` lines 50-72) is to scaffold stubs first; tasks 004-013 then *expand* these stubs rather than create new files.

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/index.ts` — add 10 import lines
  - `packages/theme-blank/resources/css/base.css` — extend `:not(:defined)` selector group
  - `packages/theme-blank/resources/js/components/index.test.ts` — test that all tags are registered
- Patterns to follow: the existing 12-line `components/index.ts` (one import per component) and the existing `:not(:defined)` block in `base.css`.

## Changes Required

### Scaffold 10 stub component files

For each of `mk-button`, `mk-input`, `mk-textarea`, `mk-select`, `mk-checkbox`, `mk-radio`, `mk-switch`, `mk-field`, `mk-fieldset`, `mk-form`, create:

1. `packages/theme-blank/resources/js/components/mk-{name}.ts` containing the minimum to register the tag:
```typescript
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-{name}.css';

export class Mk{Name}Element extends MkElement {}

registerBase('mk-{name}', Mk{Name}Element);
```

2. `packages/theme-blank/resources/css/components/mk-{name}.css` containing an empty layered block:
```css
@layer components {
  /* stub — populated by task 0XX */
}
```

Tasks 004-013 will expand the TypeScript classes (adding `@property` decorators, `connectedCallback` logic, etc.) and the CSS files (filling in the layout/state rules). Existing exports of these stubs (`MkButtonElement`, `MkFieldElement`, …) must remain stable so later tasks can extend them without renaming.

### `components/index.ts` — append 10 imports:
```typescript
import './mk-button';
import './mk-input';
import './mk-textarea';
import './mk-select';
import './mk-checkbox';
import './mk-radio';
import './mk-switch';
import './mk-field';
import './mk-fieldset';
import './mk-form';
```

### `base.css` — extend the `:not(:defined)` selector group to include:
```css
mk-button:not(:defined),
mk-input:not(:defined),
mk-textarea:not(:defined),
mk-select:not(:defined),
mk-checkbox:not(:defined),
mk-radio:not(:defined),
mk-switch:not(:defined),
mk-field:not(:defined),
mk-fieldset:not(:defined),
mk-form:not(:defined) {
  /* No declarations — @layer components tag selectors supply the real layout.
   * This selector group is retained as a documentation hook and downstream
   * override point. The trailing comment satisfies stylelint's block-no-empty rule. */
}
```

Add these new tags to the **existing** selector group (merge into one rule, don't create a second separate `:not(:defined)` block).

## Requirements (Test Descriptions)

Tests go in `packages/theme-blank/resources/js/components/index.test.ts`:

- [ ] `it scaffolds the 10 new component .ts stub files in packages/theme-blank/resources/js/components/`
- [ ] `it scaffolds the 10 new component .css stub files under packages/theme-blank/resources/css/components/, each containing only an @layer components block`
- [ ] `it the components/index.ts file imports all 10 new form component modules`
- [ ] `it registers mk-button via the components index import`
- [ ] `it registers mk-input via the components index import`
- [ ] `it registers mk-textarea via the components index import`
- [ ] `it registers mk-select via the components index import`
- [ ] `it registers mk-checkbox via the components index import`
- [ ] `it registers mk-radio via the components index import`
- [ ] `it registers mk-switch via the components index import`
- [ ] `it registers mk-field via the components index import`
- [ ] `it registers mk-fieldset via the components index import`
- [ ] `it registers mk-form via the components index import`
- [ ] `it TypeScript compiles after scaffolding (npm run typecheck or tsc --noEmit exits 0)`

For the `base.css` change, add a file-content assertion in the existing `base.test.ts`:
- [ ] `it includes mk-button, mk-input, mk-textarea, mk-select, mk-checkbox, mk-radio, mk-switch, mk-field, mk-fieldset, and mk-form in the :not(:defined) selector group`

## Acceptance Criteria
- All requirements have passing tests
- All 10 stub `.ts` files compile and export `Mk{Name}Element` classes
- All 10 stub `.css` files exist and contain `@layer components`
- The `:not(:defined)` selector group is one merged rule (not two separate rules)
- The `components/index.ts` file has exactly one import per component, in the listed order
- `tsc --noEmit` passes after task 003 in isolation (proving stubs satisfy imports)
- Stylelint passes on modified `base.css` and all 10 new component CSS stubs
