# Task 004: mk-spinner — CSS-Only Loading Indicator with Reduced-Motion Respect

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement `mk-spinner` as a light-DOM inline-block element that renders a pure-CSS spinning ring (via `border` + `@keyframes spin` rotation). Supports `size="sm|base|lg"` via CSS attribute selector mapped to `--mk-spinner-size-*` tokens. JS responsibility limited to: setting `role="status"` and `aria-live="polite"` on `connectedCallback` if not already set, and (if no child text content is present) injecting a `<span class="mk-visually-hidden">Loading…</span>` for screen readers.

The `.mk-visually-hidden` class does NOT exist anywhere in `@markommerce/theme-blank` yet. This task introduces it in `mk-spinner.css` inside `@layer components`. (It is scoped to this component file for now; a future utilities layer can promote it if other components need it.)

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/mk-spinner.ts` (expand stub)
  - `packages/theme-blank/resources/css/components/mk-spinner.css` (expand stub)
  - `packages/theme-blank/resources/js/components/mk-spinner.test.ts` (new)
- Patterns to follow: `mk-heading.ts` (ARIA injection guarded by `hasAttribute`), `mk-divider.ts` (server-renderable + role injection)

## Requirements (Test Descriptions)

- [ ] `it registers under the tag name "mk-spinner" with MkSpinnerElement extending MkElement`
- [ ] `it reflects the size attribute between property and DOM attribute`
- [ ] `it sets role="status" on connect when role is not already set`
- [ ] `it does not overwrite an existing role attribute (e.g., role="progressbar")`
- [ ] `it sets aria-live="polite" on connect when aria-live is not already set`
- [ ] `it disables the CSS animation when prefers-reduced-motion is active` (verified via getComputedStyle + media-query mock or a CSS snapshot containing the @media block)
- [ ] `it produces zero layout shift when the JS class is registered after the element renders` (DOM snapshot before/after define)
- [ ] `it defines a .mk-visually-hidden helper class inside mk-spinner.css using the standard absolute-position + clip-path recipe (width:1px, height:1px, overflow:hidden, clip-path:inset(50%), white-space:nowrap)`
- [ ] `it injects a default <span class="mk-visually-hidden">Loading…</span> on connect when the element has no child text content`
- [ ] `it does not inject a duplicate visually-hidden label on re-connection`

## Acceptance Criteria
- All requirements have passing tests
- CSS animation uses `border-{color,style,width}` + `@keyframes` rotation; respects `@media (prefers-reduced-motion: reduce) { animation: none; }`
- Default size is `base`; sm and lg use `--mk-spinner-size-sm` / `--mk-spinner-size-lg`
- Element is `display: inline-block` (so it composes with surrounding text without breaking flow)
- `.mk-visually-hidden` class is declared inside the same `mk-spinner.css` file (under `@layer components`) so the spinner can announce a screen-reader-only label without external dependencies
- The existing `registerBase('mk-spinner', MkSpinnerElement)` from task 002 remains the single registration; this task extends the existing class — DO NOT add a second `registerBase` call

## Implementation Notes
(Left blank — filled in by programmer during implementation)
