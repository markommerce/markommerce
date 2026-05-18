# Task 003: mk-alert — Static Notification with Optional Dismissible

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement `mk-alert` as a light-DOM wrapper around server-rendered alert content. Supports `variant="info|success|warning|danger"` for color theming via CSS attribute selectors. When the `dismissible` boolean attribute is set, the element injects a `<button class="mk-alert-close" aria-label="Dismiss">` on `connectedCallback`; click removes the element instantly (no animation).

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/mk-alert.ts` (expand stub)
  - `packages/theme-blank/resources/css/components/mk-alert.css` (expand stub)
  - `packages/theme-blank/resources/js/components/mk-alert.test.ts` (new)
- Patterns to follow: `mk-button.ts` (variant attribute), `mk-link.ts` (light-DOM child manipulation in `connectedCallback`)

## Requirements (Test Descriptions)

- [x] `it registers under the tag name "mk-alert" with MkAlertElement extending MkElement`
- [x] `it reflects the variant attribute between property and DOM attribute`
- [x] `it preserves server-rendered child content (does not replace innerHTML)`
- [x] `it injects a close button on connect when the dismissible attribute is present`
- [x] `it does not inject a close button when the dismissible attribute is absent`
- [x] `it removes the element from the DOM when the injected close button is clicked`
- [x] `it does not inject a duplicate close button when re-connected to the DOM`

## Acceptance Criteria
- All requirements have passing tests
- CSS shows distinct visual treatment for each variant via `mk-alert[variant="info|success|warning|danger"]` selectors using `--mk-alert-bg-*` / `--mk-alert-fg-*` / `--mk-alert-border-color-*` tokens
- Close button uses `--mk-color-fg-muted` and `cursor: pointer`; absolutely positioned in the top-right corner of the alert
- Light-DOM contract preserved: rendering `<mk-alert>{some content}</mk-alert>` keeps `{some content}` exactly as-is in the DOM tree
- The existing `registerBase('mk-alert', MkAlertElement)` from task 002 remains the single registration; this task extends the existing class — DO NOT add a second `registerBase` call (it throws `RegistryError`)

## Implementation Notes
- Extended `MkAlertElement` with `@property({ converter: stringOrUndefined, reflect: true }) variant` using the same `ComplexAttributeConverter` pattern from `mk-link.ts`
- `connectedCallback` injects `<button class="mk-alert-close" aria-label="Dismiss">` only when `dismissible` attribute is present and no close button already exists (prevents duplicates on re-connect)
- Click handler calls `this.remove()` to remove the element from the DOM instantly with no animation
- CSS uses `mk-alert[variant="..."]` attribute selectors with `--mk-alert-bg-*`, `--mk-alert-fg-*`, `--mk-alert-border-color-*` tokens; fallback values use `oklch()` with `deg` hue notation to satisfy stylelint `hue-degree-notation` rule
- Close button is absolutely positioned in top-right with `--mk-color-fg-muted` color and `cursor: pointer`
