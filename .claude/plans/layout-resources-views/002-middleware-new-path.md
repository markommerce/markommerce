# Task 002: Update CompileIfStaleMiddleware to Stat-Walk `resources/views/layout/`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Update `CompileIfStaleMiddleware::collectSourceFiles()` so that staleness detection walks `{module}/resources/views/layout/` and `{module}/resources/views/layout/extensions/` instead of the legacy `{module}/layout/` path. The middleware's gating, behaviour, and contracts are unchanged — only the directories it stats.

## Context
- Production file: `packages/layout/src/Middleware/CompileIfStaleMiddleware.php` — the hardcoded `$layoutDir = $module->path . '/layout';` at `:93` plus the nested `$layoutDir . '/extensions'` at `:106`.
- Test file: `packages/layout/tests/Unit/Middleware/CompileIfStaleMiddlewareTest.php` — the helper that scaffolds a temporary module currently builds `$baseDir . '/layout'` at `:95`. All fixture builders must shift to the new path.
- Behaviour must remain: in non-dev environments the middleware is a no-op; in dev/local it triggers a recompile only when any source file is newer than the artifact.
- Existing public API (`isStale`, `collectSourceFiles` private methods, `handle()`) does not change shape.

## Requirements (Test Descriptions)
- [ ] `it collects layout source files from resources/views/layout of registered modules`
- [ ] `it collects extension source files from resources/views/layout/extensions of registered modules`
- [ ] `it ignores modules that have no resources/views/layout directory`
- [ ] `it ignores modules that have resources/views/layout but no extensions subdirectory`
- [ ] `it triggers a recompile when a source file is newer than the artifact in dev environment`
- [ ] `it does not trigger a recompile when no source files exist`
- [ ] `it does not recompile based on files in the legacy {module}/layout directory`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/layout/src/Middleware/CompileIfStaleMiddleware.php` references only the new path.
- No legacy `'/layout'` directory literal remains in the middleware or its test.
- `./vendor/bin/phpstan analyse packages/layout/src/Middleware/CompileIfStaleMiddleware.php` passes at level 8.

## Implementation Notes
- The negative-case test (`it does not recompile based on files in the legacy {module}/layout directory`) must construct a temp module that contains both a legacy `{module}/layout/` directory with a fresher source file **and** a separate `{module}/resources/views/layout/` directory whose only file is older than the artifact. Assert that the legacy fresher file does NOT trigger a recompile.
- The existing helper `makeModuleWithLayoutFile($baseDir, $filename)` at `:93-100` currently builds `$layoutDir = $baseDir . '/layout';`. Update it to build `$baseDir . '/resources/views/layout'`. For the negative-case test only, add a sibling helper that intentionally writes into the legacy path.
