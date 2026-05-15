# Task 012: Root vite.config.ts

**Status**: completed
**Depends on**: 005, 011
**Retry count**: 0

## Description

Author the root `vite.config.ts` that wires the scanner plugin, sets the build target, declares path aliases for `@markommerce/frontend`, configures output to align with `marko/vite`'s expected manifest location (`public/build/.vite/manifest.json`), enables PostCSS via the existing config, and points the dev server at port 5173. The build input is the demo package's `main.ts` (Phase 1 only ships the demo as the entry).

## Context

- File: `vite.config.ts` (repo root).
- Build settings: `target: 'es2022'`, `manifest: true`, `emptyOutDir: true`.
- **`outDir` resolution:** `Marko\Vite\Vite::manifestPath()` builds `<ProjectPaths::base>/public/<vite.buildDirectory>/<vite.manifestFilename>`, where `base` defaults to `getcwd()` of the consuming app (verified in `marko/core/src/Path/ProjectPaths.php`). Markommerce itself has no server entry point — the playground (or another consuming app) runs the actual app. The Vite config therefore resolves `outDir` from the `MARKOMMERCE_CONSUMER_PUBLIC` env var, falling back to `<repoRoot>/public/build/` when unset. Recommended local value for the playground: `MARKOMMERCE_CONSUMER_PUBLIC=../playground/public/build`.
- Asset filenames: hashed under `<outDir>/assets/[name].[hash].[ext]` to match `marko/vite`'s default manifest reader.
- Source maps in dev; off in production.
- Dev server: port 5173, `cors: true`, `strictPort: true` so the PHP layer can rely on a fixed URL. `server.origin` set to `http://localhost:5173` so HMR URLs in the manifest are absolute.
- Aliases: `@markommerce/frontend` → `<repoRoot>/packages/frontend/resources/js/index.ts`; `@markommerce/frontend/*` → `<repoRoot>/packages/frontend/resources/js/*`; `@markommerce/frontend/css/*` → `<repoRoot>/packages/frontend/resources/css/*`.
- Entry: `packages/frontend-demo/resources/js/main.ts` (created in task 016).
- **`vite` itself must be added to the root `package.json` devDependencies** (alongside the dev deps task 001 already ships). The root config imports `defineConfig` from `vite`. Type-check requires `vite` to be installed at the workspace root.

## Requirements (Test Descriptions)

(Verified via running `vite build --mode test` and asserting the manifest's structure plus reading the config back.)

- [ ] `it loads the markommerceModuleScanner plugin with the agreed packagesPath and outputPath`
- [ ] `it sets the build target to es2022`
- [ ] `it resolves outDir from MARKOMMERCE_CONSUMER_PUBLIC env var falling back to public/build inside the repo`
- [ ] `it sets manifest to true so marko/vite can read the produced manifest under .vite/manifest.json`
- [ ] `it declares the @markommerce/frontend aliases for js and css subpaths`
- [ ] `it points the dev server at port 5173 with strictPort enabled`
- [ ] `it sources css.postcss from the repo-root postcss.config.js`
- [ ] `it sets the build input to packages/frontend-demo/resources/js/main.ts`
- [ ] `it produces hashed asset filenames under assets/`
- [ ] `vite build exits 0 once the demo package and its main entry exist`
- [ ] `the root package.json declares vite as a devDependency`

## Acceptance Criteria

- `npm run build` (executed in Docker after task 016/017) produces `public/build/.vite/manifest.json`.
- `npm run dev` boots the dev server on 5173.
- Type-check passes against the config file.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
