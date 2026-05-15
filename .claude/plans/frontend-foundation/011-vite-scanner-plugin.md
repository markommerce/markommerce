# Task 011: Implement Vite scanner plugin

**Status**: done
**Depends on**: 004, 005
**Retry count**: 0

## Description

Build the Vite plugin that scans `packages/*/package.json` for a `markommerce` block at module-load time, sorts the discovered modules by priority (kernel first, then ascending priority, alphabetical tiebreak), and emits a generated `extensions.ts` file containing side-effect imports of every module's entry point. Lives at `build/vite-plugin-markommerce.ts`. Re-runs on `package.json` HMR. Tested with Vitest against a fixture filesystem.

## Context

- File: `build/vite-plugin-markommerce.ts` (at repo root, NOT in a package — it's tooling).
- Plugin options: `packagesPath` (default `<repoRoot>/packages`), `outputPath` (default `<repoRoot>/packages/frontend-demo/resources/js/.generated/extensions.ts`).
- Discovery rules: a package is "frontend-enabled" if its `package.json` has a `markommerce.extension` field. The `extension` is resolved relative to the package directory.
- Kernel-first ordering: `@markommerce/frontend` (priority 0) sorts before everything else regardless of name; other modules sort by priority ascending then by package name alphabetically.
- Output file format: header comment + ordered side-effect imports + `export const LOADED_MODULES` array for debugging.
- Tests use `memfs` or write to a tmp dir; cover discovery, ordering, the HMR regeneration trigger, and the case where no `markommerce` block exists.

## Requirements (Test Descriptions)

- [x] `it scans packagesPath and discovers packages with a markommerce block in package.json`
- [x] `it skips packages without a markommerce block silently`
- [x] `it skips package directories that have no package.json at all (e.g. packages/core/ today)`
- [x] `it skips packages with a malformed package.json silently`
- [x] `it writes the generated extensions file to outputPath on buildStart`
- [x] `it orders @markommerce/frontend first regardless of priority value`
- [x] `it orders remaining modules by ascending priority, breaking ties alphabetically by name`
- [x] `it emits one side-effect import line per discovered module`
- [x] `it emits a LOADED_MODULES const containing name and priority for each discovered module`
- [x] `it regenerates the file on handleHotUpdate when a packages/*/package.json changes`
- [x] `it does NOT regenerate the file for unrelated package.json changes outside packagesPath`
- [x] `it resolves the extension path relative to each package directory`
- [x] `it writes the file with a "DO NOT EDIT" header comment`

## Acceptance Criteria

- All tests pass via vitest.
- Plugin exports a default function `markommerceModuleScanner(options)` returning a `Plugin` object.
- No reliance on cwd — all paths are absolute.

## Implementation Notes

- Plugin lives at `build/vite-plugin-markommerce.ts`, test at `build/vite-plugin-markommerce.test.ts`.
- Uses `import type { Plugin } from 'vite'` to avoid a runtime dependency on vite in the plugin source.
- Discovery: `fs.readdirSync(packagesPath)` → filter directories → read `package.json` → skip missing/malformed/no-markommerce-block entries.
- Sorting: kernel (`@markommerce/frontend`) always first; rest sorted by priority ascending then name alphabetically via `localeCompare`.
- Output format: DO NOT EDIT header + side-effect `import 'absolutePath';` lines + `export const LOADED_MODULES = [...]`.
- Extension paths are resolved to absolute paths via `path.resolve(pkgDir, extension)`.
- HMR match logic: `path.relative(packagesPath, file)` must have exactly 2 segments, second being `package.json`.
- All tests use Node.js `fs.mkdtempSync` in `/tmp` for fixture filesystems — no memfs needed.
- `import.meta.url` is used to resolve the repo root from the plugin file location (no cwd reliance).
