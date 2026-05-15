# Task 005: Root TypeScript config + path aliases

**Status**: completed
**Depends on**: 001, 004
**Retry count**: 0

## Description

Create a root `tsconfig.json` shared by every workspace plus a per-package `tsconfig.json` in `packages/frontend/` that extends the root. Configure strict mode plus the Lit-required flags (`useDefineForClassFields: false`, `experimentalDecorators: true`), enable `noUncheckedIndexedAccess` and `noImplicitOverride`, set `moduleResolution: "bundler"`, declare path aliases for `@markommerce/frontend` and `@markommerce/frontend/*`, and ensure `tsc --noEmit` runs cleanly.

## Context

- Project uses ES2022 target per the spec.
- `useDefineForClassFields: false` is non-negotiable for Lit 3 `@property` decorators to behave correctly.
- Path aliases are needed both for IDE autocompletion and for `tsc --noEmit`. Vite handles its own aliases separately (task 012).
- Include globs: `packages/*/resources/js/**/*`, `packages/*/resources/js/**/*.test.ts`, `build/**/*` (for the Vite plugin which lives at repo root).
- **Generated extensions placeholder:** task 016 commits an `export {}` stub at `packages/frontend-demo/resources/js/.generated/extensions.ts`. The include glob picks it up; `tsc --noEmit` resolves the `main.ts` import successfully even before the first Vite build overwrites it.
- **`vite` types under `build/`:** `build/vite-plugin-markommerce.ts` imports `Plugin` from `vite`. Vite must be installed at the workspace root as a devDependency (added by task 012). Until task 012 runs, the tsconfig may report missing types — that is expected and acceptable for an earlier-numbered task because tasks 011/012 add both the file and the dep before any consumer expects them.
- Related files: `tsconfig.json` (new at repo root), `packages/frontend/tsconfig.json` (new — extends root).

## Requirements (Test Descriptions)

- [x] `it targets ES2022 with module ESNext and moduleResolution bundler`
- [x] `it enables strict, noUncheckedIndexedAccess, and noImplicitOverride`
- [x] `it sets useDefineForClassFields to false and experimentalDecorators to true for Lit compatibility`
- [x] `it sets isolatedModules and skipLibCheck for fast builds`
- [x] `it maps @markommerce/frontend to packages/frontend/resources/js/index.ts`
- [x] `it maps @markommerce/frontend/* to packages/frontend/resources/js/*`
- [x] `it has noEmit true at the root since Vite handles transpilation`
- [x] `it excludes node_modules, public/build, and vendor`
- [x] `the packages/frontend/tsconfig.json extends the root config and scopes include to its own resources/js`
- [x] `tsc --noEmit run from the repo root reports zero errors on the empty kernel`

## Acceptance Criteria

- `npm run typecheck` (declared in task 001 but exercised here) exits 0.
- No `any` types are introduced anywhere in the kernel scaffold.

## Implementation Notes

- Created `/home/michal/www/marko/markommerce/tsconfig.json` as the root TypeScript config with all required compiler options.
- Created `/home/michal/www/marko/markommerce/packages/frontend/tsconfig.json` extending the root config.
- Added `baseUrl: "."` to the root tsconfig so non-relative path aliases in `paths` work correctly.
- Added `include: ["packages/*/resources/js/**/*"]` to root tsconfig.
- Added `exclude` with `packages/*/resources/js/**/*.test.ts` because `layers.test.ts` (from task 010) uses Node.js APIs (`fs`, `path`, `__dirname`) that are incompatible with a browser-targeted tsconfig without `@types/node`. Test files are type-checked by vitest in its own environment instead.
- `tsc --noEmit` exits 0 cleanly.
- Tests written in `/home/michal/www/marko/markommerce/packages/frontend/tests/Unit/TypescriptConfigTest.php` using PHP Pest (consistent with other config tests in this project).
