# Task 001: Package Scaffold + Manifests

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `packages/theme-blank/` package skeleton — Composer manifest, npm manifest, Marko module file, TypeScript config, directory structure, and the full manifest test suite. This task delivers an installable but functionally empty package; later tasks fill in tokens, CSS, layouts, and JS.

## Context
- Pattern to mirror: `packages/frontend/` (Composer + npm manifests, module.php returning `[]`, tsconfig extending the root) and `packages/frontend-demo/` (for the JS extension entry declaration).
- Manifest test templates: `packages/frontend/tests/Unit/ComposerManifestTest.php`, `packages/frontend/tests/Unit/NpmPackageTest.php`, `packages/frontend/tests/Unit/TypescriptConfigTest.php`, `packages/frontend/tests/Unit/LintingConfigTest.php`.
- Root `composer.json` already requires `markommerce/frontend` (production) and `markommerce/frontend-demo` (dev). `markommerce/theme-blank` is a production-grade package (consumers use it directly), so add it to the root `require` block — NOT `require-dev` — at `self.version`.
- The Vite plugin at `build/vite-plugin-markommerce` auto-discovers packages whose `package.json` contains `"markommerce": { "extension": "..." }`. Declare this in `theme-blank`'s `package.json` so the scanner picks it up. Use `priority: 100` (between `frontend` at `0` and `frontend-demo` at `1000`) so theme-level extensions load after the engine but before consumer modules.
- Directory structure to create: `src/`, `tests/Unit/`, `tests/Feature/`, `tests/Browser/`, `resources/css/`, `resources/js/`, `resources/views/layout/`, `config/`. Use `.gitkeep` files for empty dirs.
- **Root tsconfig.json paths update.** The repo-root `tsconfig.json` currently registers path aliases only for `@markommerce/frontend` and `@markommerce/frontend/*`. This task must add the analogous entries for `@markommerce/theme-blank` and `@markommerce/theme-blank/*` so consumer `import` statements resolve under `tsc --noEmit`. Without this, the `frontend-demo` typecheck breaks the moment task 002 retargets the tokens import.
- **Vite resolve.alias update.** The repo-root `vite.config.ts` currently registers explicit aliases for `@markommerce/frontend/css` and `@markommerce/frontend` (in that order, longest-prefix-first). This task must add a `@markommerce/theme-blank/css` alias pointing at `packages/theme-blank/resources/css` and a `@markommerce/theme-blank` alias pointing at `packages/theme-blank/resources/js/index.ts`, again in longest-prefix-first order. Without this, Vite cannot resolve the `import '@markommerce/theme-blank/css/tokens.css'` that task 002 introduces, even though npm workspaces would resolve it for tsc.
- **Composer manifest test convention.** The existing `ComposerManifestTest.php` in `packages/frontend` is referenced by phpunit.xml's `<testsuite name="Packages">` which globs `packages/*/tests`. New tests under `packages/theme-blank/tests/Unit/` are auto-picked-up by the same glob — no phpunit.xml change needed.
- **PHPStan.** `phpstan.neon` already scans all of `packages/` and excludes `packages/*/tests`. Once `packages/theme-blank/src/` exists with at least a `.gitkeep` (or empty namespace), PHPStan picks it up automatically. No `phpstan.neon` edit required.

## Requirements (Test Descriptions)
- [ ] `it has a composer.json declaring name markommerce/theme-blank and type marko-module`
- [ ] `it requires marko/core, marko/view, marko/view-latte, marko/vite all at self.version`
- [ ] `it requires markommerce/frontend at self.version`
- [ ] `it autoloads Markommerce\\ThemeBlank\\ from src/`
- [ ] `it autoloads Markommerce\\ThemeBlank\\Tests\\ from tests/`
- [ ] `it sets extra.marko.module to true`
- [ ] `it has a module.php returning an empty bindings array`
- [ ] `it has a tsconfig.json extending the root tsconfig and including resources/js`
- [ ] `it has a package.json named @markommerce/theme-blank with type module`
- [ ] `it declares markommerce.extension pointing at resources/js/index.ts with priority 100`
- [ ] `it declares peer dependencies on lit and open-props matching the workspace versions`
- [ ] `it exposes the css subpath via package.json exports for layers.css, tokens.css, base.css, layouts.css`
- [ ] `it has placeholder src/, tests/Unit/, tests/Feature/, tests/Browser/, resources/{css,js,views/layout}, config/ directories with .gitkeep`
- [ ] `it makes the repo-root composer.json require markommerce/theme-blank at self.version (in require, not require-dev)`
- [ ] `composer validate exits 0 on the new package manifest`
- [ ] `the root tsconfig.json registers a path alias @markommerce/theme-blank → packages/theme-blank/resources/js/index.ts`
- [ ] `the root tsconfig.json registers a path alias @markommerce/theme-blank/* → packages/theme-blank/resources/js/*`
- [ ] `the root vite.config.ts registers a resolve.alias for @markommerce/theme-blank/css → packages/theme-blank/resources/css (declared before the @markommerce/theme-blank alias for longest-prefix-first matching)`
- [ ] `the root vite.config.ts registers a resolve.alias for @markommerce/theme-blank → packages/theme-blank/resources/js/index.ts`
- [ ] `npm install at the repo root resolves @markommerce/theme-blank via the workspace (asserted via package-lock.json)`

## Acceptance Criteria
- All requirements have passing tests
- `composer install` and `npm install` complete without errors
- `composer test` discovers the new test suite
- `npm run typecheck` succeeds (tsconfig resolves)
- No decrease in test coverage

## Implementation Notes
(Left blank — filled in by programmer during implementation)
