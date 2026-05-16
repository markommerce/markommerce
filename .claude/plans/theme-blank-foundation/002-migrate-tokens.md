# Task 002: Migrate Tokens from `frontend` to `theme-blank` with `--mk-*` Rename

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Move the existing semantic design tokens out of `packages/frontend/resources/css/tokens.css` into `packages/theme-blank/resources/css/tokens.css`, renaming every custom property from its current unprefixed form (`--color-primary`, `--space-1`, `--radius-base`, …) to the `--mk-*` namespace (`--mk-color-primary`, `--mk-space-1`, `--mk-radius-base`, …). Update every consumer in the monorepo to import from the new location and use the new names. `frontend` becomes a pure engine after this task: it ships `layers.css` (the cascade-layer order) but no design tokens.

## Context
- **Existing tokens to migrate** (from `packages/frontend/resources/css/tokens.css`):
  - Colors: `--color-primary`, `--color-primary-light`, `--color-on-primary`, `--color-surface`, `--color-on-surface`, `--color-border`, `--color-error` (rename all with `--mk-` prefix)
  - Spacing: `--space-1` … `--space-5` → `--mk-space-1` … `--mk-space-5`
  - Typography: `--font-size-sm/base/lg/xl`, `--font-weight-normal/bold` → `--mk-font-size-*`, `--mk-font-weight-*`
  - Transitions: `--transition-fast`, `--transition-base` → `--mk-transition-fast`, `--mk-transition-base`
  - Radius: `--radius-sm`, `--radius-base`, `--radius-lg` → `--mk-radius-*`
  - Dark-mode rebinds inside `[data-theme="dark"]` get the same prefix treatment
- **Files to delete:**
  - `packages/frontend/resources/css/tokens.css`
- **Files to update:**
  - `packages/frontend/package.json` — remove the `./css/tokens.css` exports entry
  - `packages/frontend/resources/js/layers.test.ts` — strip the entire `describe('tokens.css', …)` block and the two "tokens.css export" tests at the bottom; keep the `describe('layers.css', …)` block. **Also remove the top-level `const tokensCss = readFileSync(resolve(cssDir, 'tokens.css'), 'utf-8');`** — otherwise the file load throws `ENOENT` at module-evaluation time after the css file is deleted, which makes every test in the file error out (not just the tokens tests).
  - `packages/frontend/resources/css/layers.css` — update the header comment that names `--color-primary` to reference `--mk-color-primary` and mention that tokens now ship from `theme-blank`
  - `packages/frontend-demo/resources/js/main.ts` — change `import '@markommerce/frontend/css/tokens.css'` to `import '@markommerce/theme-blank/css/tokens.css'`
  - `packages/frontend-demo/resources/js/package.test.ts` — update **both** assertion blocks that reference the old import path: the literal `import '@markommerce/frontend/css/tokens.css'` string check **and** the `tokensPos = content.indexOf('import \'@markommerce/frontend/css/tokens.css\'')` ordering check on lines 86 and 95. Without updating both, `indexOf` returns -1 and the ordering assertions pass by coincidence on negative numbers but with misleading semantics.
  - `packages/frontend-demo/resources/css/components/counter.css` — rename every `var(--color-primary)`, `var(--space-2)`, etc. to `var(--mk-...)`. The fallback in `var(--color-primary-hover, var(--color-primary))` becomes `var(--mk-color-primary-hover, var(--mk-color-primary))`. **Audit note:** the current file references `var(--color-text)` (line 7) which is NOT defined in the existing tokens.css and is therefore an existing dangling reference. Either (a) rename to `var(--mk-color-fg)` if the intent was foreground color (preferred — task 003 introduces `--mk-color-fg`), **or** (b) note that `--mk-color-fg` is not yet defined in task 002 (it arrives in task 003) and use `var(--mk-color-on-surface)` as the migration target since `--color-on-surface` is the closest existing equivalent. Decision: use `var(--mk-color-on-surface)` here so task 002 stays purely a rename without depending on task 003. Open Props raw vars (`--font-size-1`, `--size-4`, `--border-size-2`) stay untouched.
  - `packages/frontend-demo/tests/Feature/DemoControllerTest.php` — update the assertion strings that check for `@markommerce/frontend/css/tokens.css`. Specifically the test `it loads the @markommerce/frontend cascade layers and tokens CSS in the head before component-level CSS` at lines 376–386: change `$tokensPos = strpos($contents, '@markommerce/frontend/css/tokens.css')` to `$tokensPos = strpos($contents, '@markommerce/theme-blank/css/tokens.css')` **and** rename the test description to reflect that tokens now come from `theme-blank`. Note: `strpos` returns `false` when not found, and in PHP `false < $intPos` evaluates to true via type juggling — so the assertion would silently pass even if the rename were forgotten. Fix the assertion strings explicitly.
  - `packages/frontend-demo/composer.json` — add `markommerce/theme-blank: self.version` to `require`. Without this, the demo declares `@markommerce/theme-blank/css/tokens.css` as a JS-side import but doesn't formally depend on the Composer package, which violates the Composer/npm parity convention the demo establishes with `markommerce/frontend`.
- **Files to create:**
  - `packages/theme-blank/resources/css/tokens.css` with the renamed tokens (existing scope only; expansion happens in task 003)
- `theme-blank`'s `package.json` already declares `./css/tokens.css` in its exports map (from task 001), so importers can use `@markommerce/theme-blank/css/tokens.css` immediately.
- **Critical:** test the existing demo end-to-end after the rename — `frontend-demo` is the canary; if it still renders and all its tests pass, the migration succeeded.

## Requirements (Test Descriptions)
- [ ] `it wraps every Markommerce semantic token in @layer tokens` (theme-blank/tests for the new tokens.css)
- [ ] `it exposes the migrated --mk-color-primary, --mk-color-primary-light, --mk-color-on-primary, --mk-color-surface, --mk-color-on-surface, --mk-color-border, --mk-color-error tokens`
- [ ] `it exposes the migrated --mk-space-1 through --mk-space-5 tokens`
- [ ] `it exposes the migrated --mk-font-size-sm, --mk-font-size-base, --mk-font-size-lg, --mk-font-size-xl tokens`
- [ ] `it exposes the migrated --mk-font-weight-normal and --mk-font-weight-bold tokens`
- [ ] `it exposes the migrated --mk-transition-fast and --mk-transition-base tokens`
- [ ] `it exposes the migrated --mk-radius-sm, --mk-radius-base, --mk-radius-lg tokens`
- [ ] `it rebinds the appropriate --mk-color-* tokens inside [data-theme="dark"]`
- [ ] `it is exported from theme-blank package.json so import '@markommerce/theme-blank/css/tokens.css' resolves`
- [ ] `the frontend package no longer ships tokens.css` (assert file does not exist)
- [ ] `the frontend package.json exports no longer declares ./css/tokens.css`
- [ ] `the frontend layers.test.ts no longer asserts presence of unprefixed tokens`
- [ ] `the frontend-demo main.ts imports @markommerce/theme-blank/css/tokens.css (not @markommerce/frontend/css/tokens.css)`
- [ ] `the frontend-demo counter.css uses --mk-* prefixed token references exclusively for markommerce tokens`
- [ ] `the frontend-demo counter.css no longer references the undefined --color-text token` (regression-fix guard)
- [ ] `the existing frontend-demo Feature DemoControllerTest passes against the updated import paths`
- [ ] `the frontend-demo Feature DemoControllerTest asserts ordering against @markommerce/theme-blank/css/tokens.css (not the old frontend path) so the strpos==false type-juggling false-positive is eliminated`
- [ ] `the frontend layers.test.ts no longer eagerly reads tokens.css at module scope (asserted by reading the test file source — it should not contain readFileSync(.../tokens.css))`
- [ ] `the frontend-demo composer.json requires markommerce/theme-blank at self.version`

## Acceptance Criteria
- All requirements have passing tests
- `composer test` (full Pest suite) passes — including the existing `frontend-demo` feature tests
- `npm run test` (Vitest) passes
- `npm run lint:css` and `npm run lint:js` pass
- No occurrences of unprefixed `var(--color-primary)`, `var(--space-1)`, etc. in any package source (except inside `@markommerce/theme-blank/resources/css/tokens.css` where they are *defined*, not *referenced*)
- The frontend-demo page renders identically in the browser before and after this task (manual smoke test if available)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
