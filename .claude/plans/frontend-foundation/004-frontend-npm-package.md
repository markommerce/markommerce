# Task 004: Scaffold @markommerce/frontend npm workspace package

**Status**: complete
**Depends on**: 001, 003
**Retry count**: 0

## Description

Add a `package.json` to `packages/frontend/` declaring the npm-side artefact name `@markommerce/frontend`. This is the JS-side companion to the Composer package created in task 003. Declare `lit` and `open-props` as peer dependencies so consumer apps install the canonical versions, list `vitest`, `happy-dom`, `typescript`, `eslint`, `stylelint`, and `prettier` as dev dependencies, and surface a `markommerce` block (`extension`, `priority`) so the Vite scanner plugin can discover it. Also create the `resources/js/` and `resources/css/` directory placeholders.

## Context

- Workspace name: `@markommerce/frontend`. Resolves via npm workspaces (no publishing in Phase 1).
- `priority: 0` because this is the kernel — must register before any other module's mixins.
- `extension: "./resources/js/index.ts"` — the side-effect entry that calls registerBase / registerHook.
- `peerDependencies`: `lit: ^3.0`, `open-props: ^1.7` (pin a known-good minor).
- `devDependencies` (workspace local): `vitest: ^2`, `happy-dom: ^15`, `typescript: ^5`, `@open-wc/testing-helpers: ^3` (for component fixtures inside vitest), `lit` itself for the kernel's own type imports.
- Related files: `packages/frontend/package.json` (new), `packages/frontend/resources/js/index.ts` (new — empty placeholder for now; task 007–009 add real content), `packages/frontend/resources/css/.gitkeep`.

## Requirements (Test Descriptions)

- [x] `it has a package.json declaring name @markommerce/frontend and type module`
- [x] `it sets the markommerce.extension to ./resources/js/index.ts`
- [x] `it sets markommerce.priority to 0 marking this as the kernel`
- [x] `it declares lit and open-props as peer dependencies with the agreed version ranges`
- [x] `it declares vitest, happy-dom, typescript, and lit as dev dependencies`
- [x] `it exposes resources/js/index.ts as the package main field`
- [x] `it exports . pointing at ./resources/js/index.ts for downstream imports`
- [x] `it creates an empty resources/js/index.ts with the strict-mode preamble comment`
- [x] `npm install at the repo root resolves @markommerce/frontend via the workspace`

## Acceptance Criteria

- `npm install` (executed by humans) succeeds.
- `npm ls @markommerce/frontend` shows the workspace package.
- `resources/js/index.ts` parses (even if empty).
- Directory layout: `packages/frontend/{composer.json, package.json, module.php, src/, tests/, resources/js/, resources/css/}`.

## Implementation Notes

- Created `packages/frontend/package.json` with name `@markommerce/frontend`, type `module`, markommerce block, peerDependencies, and devDependencies.
- Created `packages/frontend/resources/js/index.ts` as an empty ES module placeholder with the `// Kernel entry` comment.
- Created `packages/frontend/resources/css/.gitkeep` to track the css directory.
- npm install was run via the Docker node service (`docker compose up -d node && npm install`). The node_modules live in a Docker volume so the workspace resolution is verified via `package-lock.json` on the local filesystem.
- All 9 PHP Pest tests written in `packages/frontend/tests/Unit/NpmPackageTest.php`.
