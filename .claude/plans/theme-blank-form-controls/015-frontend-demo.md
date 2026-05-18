# Task 015: Extend Frontend-Demo with "Form Controls" Section

**Status**: completed
**Depends on**: 004, 005, 006, 007, 008, 009, 010, 011, 012, 013
**Retry count**: 0

## Description
Extend the existing `/markommerce/_demo` Latte view with a "Form Controls" section that demonstrates all 10 form control components with realistic examples, including a working `mk-form` with `mk-field` validation.

## Context
- Related files:
  - `packages/frontend-demo/resources/views/counter.latte` — the existing demo Latte view; append the new "Form Controls" section here.
  - `packages/frontend-demo/src/Controller/DemoController.php` — controller (no changes needed; it has no data to pass).
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php` — append new tests here.
  - Reference: the "Layout primitives" / "Typography primitives" sections already in `counter.latte` for the grouping pattern.
- Read `counter.latte` first to understand the current structure before making changes.
- The new section should appear after the existing "Typography primitives" section, wrapped in `<section class="demo-form-controls">`.

## Section Content

The "Form Controls" section should be grouped into subsections:

### Sub-section: Individual Controls
Show each control in isolation with labels:
- `mk-button` — all variants (primary, secondary, ghost, danger) + loading state
- `mk-input` — outline and filled variants, sm/base/lg sizes
- `mk-textarea` — outline and filled variants
- `mk-select` — with a few options
- `mk-checkbox` — single and with label inline
- `mk-radio` — group of 3 options
- `mk-switch` — on and off states

### Sub-section: Field + Validation
A realistic `mk-field` usage showing:
- Field with hint text
- Field with required constraint (show error state)
- Field with `data-state="validating"` appearance

### Sub-section: Complete Form
A working `mk-form` with:
- Name input (required)
- Email input (required, type="email")
- Country select
- Terms checkbox (required)
- Submit button

The form should be wired with inline `<script>` to demonstrate `mk-submit` event handling (console.log the FormData entries) and `mk-invalid` event (console.log invalid fields).

**Latte gotcha**: Latte parses `{...}` as expressions. Any inline JavaScript containing `{` (object literals, arrow function bodies, template strings) will break Latte's parser. Wrap inline `<script>` blocks in `{syntax off}…{/syntax}`:

```latte
{syntax off}
<script>
  document.querySelector('mk-form')?.addEventListener('mk-submit', (e) => {
    console.log('submit:', [...e.detail.formData.entries()]);
  });
</script>
{/syntax}
```

## Requirements (Test Descriptions)

Tests go in `packages/frontend-demo/tests/Feature/DemoControllerTest.php` (the existing demo controller feature test). Follow the one-assertion-per-element precedent set by Phase 2 (see existing `it the rendered page contains at least one <mk-X> element` tests):

- [ ] `it renders the Form Controls section heading in the demo view`
- [ ] `it the rendered page contains at least one <mk-button> element`
- [ ] `it the rendered page contains at least one <mk-input> element`
- [ ] `it the rendered page contains at least one <mk-textarea> element`
- [ ] `it the rendered page contains at least one <mk-select> element`
- [ ] `it the rendered page contains at least one <mk-checkbox> element`
- [ ] `it the rendered page contains at least one <mk-radio> element`
- [ ] `it the rendered page contains at least one <mk-switch> element`
- [ ] `it the rendered page contains at least one <mk-field> element`
- [ ] `it the rendered page contains at least one <mk-fieldset> element`
- [ ] `it the rendered page contains at least one <mk-form> element`
- [ ] `it renders a complete mk-form example with at least one mk-field child`

## Acceptance Criteria
- All requirements have passing tests
- `composer test` still passes
- The demo page renders without JavaScript errors in the browser (verify manually or via existing Playwright setup)
- Each form control is visually labelled so the purpose is clear
- The complete form section has realistic field names/labels (not "foo"/"bar")
