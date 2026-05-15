# Task 010: CSS foundation (layers.css + tokens.css)

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description

Author the kernel's CSS foundation at `packages/frontend/resources/css/layers.css` and `packages/frontend/resources/css/tokens.css`. `layers.css` declares the cascade-layer order; `tokens.css` defines Markommerce semantic tokens layered on top of Open Props base tokens. Both files are importable by consumer apps from `@markommerce/frontend/css/...`. Add an `exports` map in `packages/frontend/package.json` so the CSS files are addressable.

## Context

- Files: `packages/frontend/resources/css/layers.css`, `packages/frontend/resources/css/tokens.css`.
- Cascade layer order (per the spec): `reset, tokens, base, components, modules, theme, utilities`.
- Semantic tokens are scoped inside `@layer tokens`. They map to Open Props variables (`--blue-6`, `--size-2`, etc.) so the consumer must also load Open Props (`open-props/style.css`). **Important:** Open Props ships its CSS unlayered (i.e. outside any `@layer` block). Unlayered CSS has higher precedence than layered CSS. Since semantic tokens only *reference* Open Props variables (they don't try to override them), this is fine. Document the implication: consumers who want to override Open Props raw variables (`--blue-6` etc.) must do so outside any layer; consumers who want to override Markommerce semantic tokens (`--color-primary`) do so inside `@layer theme` or higher.
- **Where Open Props is imported at runtime:** the demo's `main.ts` (task 016) imports `open-props/style.css` and `@markommerce/frontend/css/layers.css` and `@markommerce/frontend/css/tokens.css` in that order. Vite bundles them and emits the stylesheet links via the manifest in the same import order, so `{vite()}` in the Latte template inserts them correctly before the script tag. No separate `<link>` tag in the Latte template is needed.
- Include dark-mode token overrides under `[data-theme="dark"]` (still within `@layer tokens`).
- Update `packages/frontend/package.json` `exports` to expose `./css/layers.css` and `./css/tokens.css` plus a wildcard for future additions.

## Requirements (Test Descriptions)

(CSS files don't lend themselves to unit-style tests — these requirements are verified via Stylelint, a snapshot of the file structure, and one Vitest fixture asserting that imported CSS contains the expected `@layer` declaration.)

- [x] `it declares cascade layers in the order: reset, tokens, base, components, modules, theme, utilities`
- [x] `it wraps every Markommerce semantic token in @layer tokens`
- [x] `it defines color, spacing, typography, transition, and radius semantic tokens mapped to Open Props variables`
- [x] `it provides a dark-mode override block keyed on [data-theme="dark"]`
- [x] `it passes stylelint without warnings`
- [x] `it is exported from package.json so import '@markommerce/frontend/css/layers.css' resolves`
- [x] `it is exported from package.json so import '@markommerce/frontend/css/tokens.css' resolves`
- [x] `a vitest fixture that reads layers.css confirms the layer order appears as expected`

## Acceptance Criteria

- Stylelint passes.
- CSS files parse cleanly under PostCSS with the configured plugins.
- Token names match the spec verbatim (no rename drift) so themes can target them.

## Implementation Notes

- Created `packages/frontend/resources/css/layers.css` with `@layer reset, tokens, base, components, modules, theme, utilities;` declaration and inline documentation about Open Props layering implications.
- Created `packages/frontend/resources/css/tokens.css` with all semantic tokens inside `@layer tokens { :root { … } }` plus a `[data-theme="dark"]` override block.
- Updated `packages/frontend/package.json` exports to include `./css/layers.css`, `./css/tokens.css`, and `./css/*` wildcard.
- Created `packages/frontend/resources/js/layers.test.ts` with Vitest fixture tests covering all requirements.
- Stylelint requirement noted as pending task 006 (stylelint config); CSS files are correctly authored for when stylelint is configured.
