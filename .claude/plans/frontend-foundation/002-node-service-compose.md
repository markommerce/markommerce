# Task 002: Add Node service to compose.yaml

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description

Extend `/home/michal/www/marko/compose.yaml` (the shared Docker compose for the playground + marko + markommerce volumes) with a `node` service running `node:22-alpine`. The service mounts the markommerce workspace, idles by default (so devs run commands via `docker compose exec node ...`), and exposes port 5173 for the Vite dev server. Existing `app`, `postgres`, `redis` services remain unchanged.

## Context

- **Compose file is OUTSIDE the markommerce repo.** Path: `/home/michal/www/marko/compose.yaml` (one level above markommerce, shared across marko/playground/markommerce). Editing requires referencing this absolute path; from inside the markommerce checkout it is `../compose.yaml`.
- CLAUDE.local.md mandates "commands run inside Docker".
- The Node service is workspace-aware but does not auto-run any command; it idles so a developer types `docker compose exec node npm run dev` explicitly.
- Volume layout must mirror the `app` service's mounts of `./marko`, `./markommerce`, `./playground` (relative to the compose file directory, which contains all three repos).
- Working directory: `/workspace/markommerce`.
- Port: publish `5173:5173` for HMR; Vite's default.
- Related files: `/home/michal/www/marko/compose.yaml` (existing — extend).
- Persistent node_modules avoids re-installing across container restarts; use a named volume `node-modules-cache` mounted at `/workspace/markommerce/node_modules`.
- **Docker-on-Linux UID gotcha:** `node:22-alpine` runs as the `node` user (UID 1000) by default. On Linux hosts where the developer's UID differs, files written by the container (`package-lock.json`, generated `extensions.ts`, etc.) end up owned by the wrong UID. Use `user: "${UID:-1000}:${GID:-1000}"` on the service so it inherits the invoking user's UID/GID at runtime, and document that contributors export `UID` and `GID` env vars (or rely on the defaults if their host UID is 1000).

## Requirements (Test Descriptions)

- [x] `it declares a node service using node:22-alpine image`
- [x] `it names the container marko-playground-node for consistency with other services`
- [x] `it mounts ./marko, ./markommerce, ./playground at /workspace/ matching the app service`
- [x] `it sets working_dir to /workspace/markommerce`
- [x] `it publishes port 5173 for Vite HMR`
- [x] `it idles with tail -f /dev/null so commands run via docker compose exec`
- [x] `it adds a named volume for node_modules to avoid host filesystem cost`
- [x] `it sets the service user to ${UID:-1000}:${GID:-1000} so container writes inherit the host UID/GID`
- [x] `it preserves all existing app, postgres, and redis configuration verbatim`

## Acceptance Criteria

- `docker compose -f ~/www/marko/compose.yaml config` (validation) exits 0.
- Existing services boot unchanged.
- `docker compose exec node node --version` reports v22.x.

## Implementation Notes

- Added `node` service to `/home/michal/www/marko/compose.yaml` with all required configuration.
- Named volume `node-modules-cache` declared at top-level volumes and mounted at `/workspace/markommerce/node_modules` to avoid host filesystem overhead and permission issues.
- `user: "${UID:-1000}:${GID:-1000}"` allows the container to inherit the host UID/GID at runtime; contributors on Linux with non-1000 UIDs must export `UID` and `GID` before running compose commands.
- Validated with `docker compose -f /home/michal/www/marko/compose.yaml config` — exits 0.
- Existing `app`, `postgres`, and `redis` services are verbatim unchanged.
