# Task 015: Dev Middleware `CompileIfStaleMiddleware`

**Status**: completed
**Depends on**: 014
**Retry count**: 0

## Description
Create the development middleware that keeps the compiled artifact fresh without a separate watcher process. On each request (dev mode only), it compares the mtime of every layout source file against the artifact; if any source is newer — or the artifact is missing — it recompiles synchronously before the request proceeds.

## Context
- Implement as a `marko/routing` middleware (`MiddlewareInterface` with `handle(Request, callable $next): Response`).
- Staleness check: collect the mtime of every `<module>/layout/**/*.php` file (reuse the discovery scanner's file list from task 008) and the `LayoutDefinition` class files reachable via `extends`; compare the max against the artifact file's mtime. Missing artifact = stale.
- If stale: invoke the shared `Compiler` service that task 014 extracted (discovery → resolution → validation → write). Do NOT duplicate the pipeline wiring — depend on the same service the command uses.
- A compile error in dev should surface loudly — let the layout exception propagate (so the dev sees the formatted error page) rather than swallowing it.
- This middleware must be a no-op / not registered in production. Gate on environment: register it only when `APP_ENV` (or Marko's equivalent) is `dev`/`local`. Document how the gating is wired (module.php conditional binding, or an env check inside `handle()`).
- Performance: the mtime scan is the per-request cost — keep it to `stat` calls only, no file parsing.
- Patterns to follow: `marko/layout/src/Middleware/LayoutMiddleware.php` for middleware shape; `marko/core` for environment detection.

## Requirements (Test Descriptions)
- [ ] `it recompiles when a layout source file is newer than the artifact`
- [ ] `it recompiles when the artifact file is missing`
- [ ] `it does not recompile when the artifact is newer than all sources`
- [ ] `it lets the request proceed after a successful recompile`
- [ ] `it lets a compile error propagate instead of swallowing it`
- [ ] `it is inactive outside the dev environment`

## Acceptance Criteria
- All requirements have passing tests
- The compile pipeline is shared with `layout:compile`, not duplicated
- Middleware is gated to dev environments only
- Per-request cost is `stat`-only — no parsing
- Environment gating mechanism documented in Implementation Notes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
