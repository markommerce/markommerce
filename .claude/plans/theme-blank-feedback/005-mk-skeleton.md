# Task 005: mk-skeleton — Loading Placeholder with Shimmer

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement `mk-skeleton` as a light-DOM block element that renders a shimmering placeholder for content that is loading. Supports `variant="text|circle|rect"` (default `rect`) via CSS attribute selector. Shimmer animation is pure CSS via `background-position` movement on a fixed `linear-gradient`, GPU-accelerated, respects `prefers-reduced-motion`. JS responsibility: set `aria-hidden="true"` and `aria-busy` is expected on parent (documented, not auto-injected).

## Context
- Related files:
  - `packages/theme-blank/resources/js/components/mk-skeleton.ts` (expand stub)
  - `packages/theme-blank/resources/css/components/mk-skeleton.css` (expand stub)
  - `packages/theme-blank/resources/js/components/mk-skeleton.test.ts` (new)
- Patterns to follow: `mk-spinner.ts` (ARIA injection), `mk-badge.ts` (variant attribute)

## Requirements (Test Descriptions)

- [x] `it registers under the tag name "mk-skeleton" with MkSkeletonElement extending MkElement`
- [x] `it reflects the variant attribute between property and DOM attribute (text|circle|rect)`
- [x] `it sets aria-hidden="true" on connect when aria-hidden is not already set`
- [x] `it does not overwrite an existing aria-hidden attribute`
- [x] `it disables the shimmer animation when prefers-reduced-motion is active`
- [x] `it produces zero layout shift when the JS class is registered after the element renders`

## Acceptance Criteria
- All requirements have passing tests
- CSS for each variant:
  - `text`: `height: 1em; border-radius: var(--mk-skeleton-radius); display: inline-block; min-width: 4em;`
  - `circle`: `border-radius: 50%; aspect-ratio: 1; height: 2em; width: 2em;` (overridable via inline style)
  - `rect`: `height: 6em; width: 100%; border-radius: var(--mk-skeleton-radius);` (overridable via inline style)
- Shimmer: `background: linear-gradient(90deg, var(--mk-skeleton-bg), var(--mk-skeleton-shimmer-color), var(--mk-skeleton-bg)); background-size: 200% 100%; animation: mk-skeleton-shimmer var(--mk-skeleton-duration) infinite linear;`
- `@media (prefers-reduced-motion: reduce) { mk-skeleton { animation: none; } }`
- The existing `registerBase('mk-skeleton', MkSkeletonElement)` from task 002 remains the single registration; this task extends the existing class — DO NOT add a second `registerBase` call

## Implementation Notes
(Left blank — filled in by programmer during implementation)
