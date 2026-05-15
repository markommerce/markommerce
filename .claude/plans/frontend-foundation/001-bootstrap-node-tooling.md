# Task 001: Bootstrap repo-level Node tooling

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description

Create the root-level Node tooling files that turn the markommerce monorepo into an npm workspace. This includes a root `package.json` declaring `packages/*` as workspaces, a `.nvmrc` pinning Node version, and `.gitignore` entries for `node_modules/`, build output, and Vite caches. No PHP changes here — purely the JS-side foundation that later tasks build on.

## Context

- Markommerce currently has no Node tooling: no `package.json`, no `node_modules`, no `.nvmrc`.
- Existing `.gitignore` is short and PHP-centric; we need to add JS entries.
- Node version target: 22 LTS (matches the playground's intended Node service version in the spec).
- npm workspaces are the right tool because every `packages/*` package that ships frontend assets will have its own `package.json` (per the discovery decision).
- Build output destination: `public/build/` (aligns with `marko/vite`'s default `vite.buildDirectory`). The real consumer-app output location is configurable via the `MARKOMMERCE_CONSUMER_PUBLIC` env var (see task 012); the in-repo path is the fallback.
- **Existing packages without `package.json`:** `packages/core/` currently has no `package.json`. npm workspaces tolerates this (the directory is simply skipped) — verified intentional, no action needed beyond noting it in this task's docs.
- Related files: `/home/michal/www/marko/markommerce/package.json` (new), `/home/michal/www/marko/markommerce/.nvmrc` (new), `/home/michal/www/marko/markommerce/.gitignore` (existing — extend).

## Requirements (Test Descriptions)

- [ ] `it has a root package.json with type "module" and private: true`
- [ ] `it declares packages/* as the npm workspaces glob`
- [ ] `it has root scripts: dev, build, preview, test, typecheck, lint, lint:fix`
- [ ] `it pins Node version to 22 in .nvmrc`
- [ ] `it ignores node_modules at any depth`
- [ ] `it ignores public/build and .vite cache dirs`
- [ ] `it ignores packages/*/resources/js/.generated/ so committed placeholders are the only generated artefacts in source control`
- [ ] `it does not declare any runtime dependencies in the root manifest (workspaces only)`
- [ ] `it does not list vite/eslint/etc. as devDependencies yet — those are added by the tasks that introduce them (006 for lint, 012 for vite)`

## Acceptance Criteria

- Root `package.json` valid JSON, parses without error.
- `npm install` from repo root (when run by humans later) would resolve cleanly because no deps are declared yet.
- `.gitignore` retains all prior PHP-specific rules.
- No existing files are deleted or restructured.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
