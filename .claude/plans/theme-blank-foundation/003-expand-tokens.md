# Task 003: Expand the `--mk-*` Token Set

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Augment `packages/theme-blank/resources/css/tokens.css` with the additional token categories required by the blank theme: full status-color palette (success/warning/danger/info with hover/active variants), full typography scale (xs through 3xl plus line-height and medium weight), spacing 0–9 (currently only 1–5), additional radii (full), shadows (sm/md/lg), motion easings (ease-out, ease-in-out) plus a slow duration, and breakpoints expressed as CSS custom-media for use with `@media (--mk-breakpoint-sm)` syntax.

## Context
- Build on top of the renamed tokens delivered by task 002 — don't duplicate or replace; *append*.
- All new tokens map to Open Props raw vars where they exist; pick reasonable Open Props names by reviewing `node_modules/open-props/open-props.min.css` if needed.
- CSS custom media requires PostCSS plugin `postcss-custom-media` — already in `package.json` devDependencies. Declare custom media at the top level of `tokens.css` (outside the `@layer tokens` block, since `@custom-media` is a top-level at-rule), but document this in a comment so future readers know why.
- The new token categories follow the brief exactly:
  - **Colors**: `--mk-color-fg`, `--mk-color-bg`, `--mk-color-fg-muted`, plus `--mk-color-success`, `--mk-color-warning`, `--mk-color-danger`, `--mk-color-info` (and `*-fg` variants where they're used as background fills). Add hover/active variants only where useful (`--mk-color-primary-hover`, `--mk-color-primary-active`).
  - **Spacing**: extend to `--mk-space-0` through `--mk-space-9`. **Mapping note:** Open Props ships `--size-000`, `--size-00`, then `--size-1` through `--size-15` — there is no literal `--size-0`. The mapping is therefore:
    - `--mk-space-0: 0` (literal zero — semantic "no space", used by `body { margin: 0 }` and reset rules; not mapped to an Open Props value)
    - `--mk-space-1` through `--mk-space-9` → `--size-1` through `--size-9` (existing 1–5 from task 002 are unchanged; this task adds 0 and 6–9)
    Pin this mapping in `tokens.css` as a comment so future reviewers don't try to "fix" `--mk-space-0` to point at `--size-0`.
  - **Typography**: `--mk-font-sans`, `--mk-font-mono`, `--mk-font-size-xs`, `--mk-font-size-2xl`, `--mk-font-size-3xl`, `--mk-line-height-tight`, `--mk-line-height-normal`, `--mk-line-height-loose`, `--mk-font-weight-medium`.
  - **Radii**: add `--mk-radius-full` (mapped to Open Props `--radius-round` or `9999px`).
  - **Shadows**: `--mk-shadow-sm`, `--mk-shadow-md`, `--mk-shadow-lg` (mapped to Open Props `--shadow-*`).
  - **Motion**: `--mk-duration-fast` already exists; add `--mk-duration-normal`, `--mk-duration-slow`, `--mk-ease-out`, `--mk-ease-in-out`. (`--mk-transition-fast` and `--mk-transition-base` from task 002 stay — those are composite shorthand values; the new ones are atomic primitives.)
  - **Breakpoints**: `@custom-media --mk-breakpoint-sm (min-width: 640px)`, `--mk-breakpoint-md (min-width: 768px)`, `--mk-breakpoint-lg (min-width: 1024px)`, `--mk-breakpoint-xl (min-width: 1280px)`.
- Keep dark-mode rebinds in `[data-theme="dark"]` synced for any new colors that need them (status colors typically don't; foreground/background do).

## Requirements (Test Descriptions)
- [ ] `it exposes --mk-color-fg, --mk-color-bg, --mk-color-fg-muted as semantic foreground/background tokens`
- [ ] `it exposes --mk-color-success, --mk-color-warning, --mk-color-danger, --mk-color-info status colors`
- [ ] `it exposes --mk-color-primary-hover and --mk-color-primary-active variants`
- [ ] `it exposes --mk-space-0 as literal 0 (not mapped to an Open Props var since --size-0 does not exist) and --mk-space-6 through --mk-space-9 mapped to --size-6 through --size-9 (extending the existing 1–5 range)`
- [ ] `it exposes --mk-font-sans and --mk-font-mono font-family tokens`
- [ ] `it exposes --mk-font-size-xs, --mk-font-size-2xl, --mk-font-size-3xl (extending the sm–xl range from task 002)`
- [ ] `it exposes --mk-line-height-tight, --mk-line-height-normal, --mk-line-height-loose`
- [ ] `it exposes --mk-font-weight-medium (in addition to the normal/bold pair from task 002)`
- [ ] `it exposes --mk-radius-full`
- [ ] `it exposes --mk-shadow-sm, --mk-shadow-md, --mk-shadow-lg`
- [ ] `it exposes --mk-duration-normal, --mk-duration-slow, --mk-ease-out, --mk-ease-in-out`
- [ ] `it declares @custom-media --mk-breakpoint-sm, --mk-breakpoint-md, --mk-breakpoint-lg, --mk-breakpoint-xl at the top level (outside @layer tokens)`
- [ ] `it rebinds --mk-color-fg and --mk-color-bg inside [data-theme="dark"]`
- [ ] `it keeps the migrated task-002 tokens intact (regression guard)`

## Acceptance Criteria
- All requirements have passing tests
- `npm run lint:css` passes (stylelint accepts custom-media syntax via the configured PostCSS plugin)
- `npm run test` passes
- No regression in task-002 token tests

## Implementation Notes
(Left blank — filled in by programmer during implementation)
