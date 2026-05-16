# Task 004: Base Styles (`base.css` in `@layer base`)

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Ship the minimal CSS reset and document-level defaults for the blank theme. `base.css` lives inside `@layer base` and references the `--mk-*` tokens delivered in tasks 002–003. It is deliberately minimal — only what's needed for sane document-level defaults. The `:not(:defined)` custom-element safety net is documented in this file as a commented convention; the rule itself stays empty in Phase 1 because no `mk-*` elements exist yet (Phase 2 populates it).

## Context
- File to create: `packages/theme-blank/resources/css/base.css`
- Wrap all rules in `@layer base { ... }`. The `frontend` `layers.css` already declares the order — `base` sits between `tokens` and `components`.
- Minimum required rules:
  - `*, *::before, *::after { box-sizing: border-box; }`
  - `body { font-family: var(--mk-font-sans); font-size: var(--mk-font-size-base); line-height: var(--mk-line-height-normal); color: var(--mk-color-fg); background-color: var(--mk-color-bg); margin: 0; }`
  - `h1, h2, h3, h4, h5, h6 { line-height: var(--mk-line-height-tight); font-weight: var(--mk-font-weight-bold); margin: 0; }` plus a size mapping (e.g. `h1: 3xl`, `h2: 2xl`, `h3: xl`, `h4: lg`, `h5: base`, `h6: sm`)
  - `a { color: var(--mk-color-primary); text-decoration: underline; }` and `a:hover { color: var(--mk-color-primary-hover); }`
  - `img, video { max-width: 100%; height: auto; display: block; }`
- Add a top-of-file documentation comment that captures the CLS-prevention convention:
  ```css
  /*
   * Markommerce custom-element CLS safety-net convention.
   *
   * When `mk-*` components arrive (Phase 2+), this file will contain a rule of the
   * shape `mk-button:not(:defined), mk-input:not(:defined), ... { visibility: hidden; }`
   * that prevents Flash-of-Undefined-Custom-Element layout shifts.
   *
   * Phase 1 ships no components, so the rule is intentionally empty. Components
   * that follow the architectural rule ("render correctly without JS") do not
   * strictly need this safety net, but it remains a defensive backstop.
   */
  ```
- Export from `package.json` — task 001 already declared `./css/base.css` in exports.
- Add `@markommerce/theme-blank/css/base.css` import to `frontend-demo/resources/js/main.ts` between the tokens import and the extensions import, so the demo continues to demonstrate the full stack. Update the demo's `package.test.ts` import-order assertion accordingly.

## Requirements (Test Descriptions)
- [ ] `it wraps all rules in @layer base`
- [ ] `it sets box-sizing border-box on all elements and pseudo-elements`
- [ ] `it sets body font-family from --mk-font-sans and font-size from --mk-font-size-base`
- [ ] `it sets body color from --mk-color-fg and background-color from --mk-color-bg`
- [ ] `it removes default body margin`
- [ ] `it sets heading line-height from --mk-line-height-tight and font-weight from --mk-font-weight-bold`
- [ ] `it maps h1 through h6 to the --mk-font-size-* scale (h1=3xl, h2=2xl, h3=xl, h4=lg, h5=base, h6=sm)`
- [ ] `it styles anchors with --mk-color-primary and underlines them`
- [ ] `it constrains img and video to max-width 100% with height auto and display block`
- [ ] `it includes a documentation comment describing the :not(:defined) CLS safety-net convention`
- [ ] `it is exported from theme-blank package.json so import '@markommerce/theme-blank/css/base.css' resolves`
- [ ] `the frontend-demo main.ts imports @markommerce/theme-blank/css/base.css between tokens.css and the extensions import`
- [ ] `the frontend-demo package.test.ts asserts the new base.css import position`

## Acceptance Criteria
- All requirements have passing tests
- `composer test` and `npm run test` both pass
- `npm run lint:css` passes
- The `frontend-demo` page still renders correctly with the additional base.css import

## Implementation Notes
(Left blank — filled in by programmer during implementation)
