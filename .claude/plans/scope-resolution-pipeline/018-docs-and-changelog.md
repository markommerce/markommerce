# Task 018: Documentation and Changelog Updates

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 016, 017
**Retry count**: 0

## Description
Add CHANGELOG entries in both repos. Update the scope package README and the docs site page with a "Scope Resolution" section explaining the pipeline, configuration shape, built-in resolvers, channel semantics, and how lifecycle hooks integrate. Document the marko-side change in marko's CHANGELOG.

## Context
- Target files (markommerce):
  - `packages/scope/CHANGELOG.md` — append new entry
  - `packages/scope/README.md` — add "Auto-resolving scope from requests" section
  - `docs/src/content/docs/packages/scope.md` (or wherever scope docs live — confirm via `ls docs/src/content/docs/packages/`)
  - Follow content standards in `docs/DOCS-STANDARDS.md`
- Target files (marko, in `/home/michal/www/marko/marko/`):
  - `marko/packages/core/CHANGELOG.md` — entry for module-declared global middleware
- Content to cover in the README/docs page:
  - **What it does** — one paragraph: pipeline resolves current scope per axis before user code runs.
  - **Configuration** — show the `'resolvers' => [...]` config block under an axis. Document both class-string and array forms. Document order matters (first non-null wins).
  - **Built-in resolvers** — short table or list: `CookieResolver`, `HeaderResolver`, `SubdomainResolver`, `PathPrefixResolver`, `QueryParamResolver`, `StaticResolver`, `AcceptLanguageResolver` — with their constructor params.
  - **Channel semantics** — HTTP resolvers no-op on CLI/queue; `StaticResolver` is universal.
  - **Cross-axis dependencies** — axes resolve in registration order; resolvers can read already-resolved axes via `$context->resolved`.
  - **Lifecycle hooks** — HTTP via auto-registered middleware (mention marko ≥ X required), CLI via plugin on `CommandInterface`. Queue is **manual opt-in** via `JobScopeWrapper::withScope()` — explain that Marko's queue Worker deserializes jobs and invokes `handle()` outside the container, so plugin auto-wiring is not possible.
  - **CLI cleanup caveat** — `#[After]` does not run when a CLI command throws; document that the `#[Before]` hook defensively clears state at the start of each command to compensate.
  - **Writing a custom resolver** — short example implementing `ScopeAxisResolverInterface`.
  - **Error behavior** — resolver failures are caught and logged; the chain continues; never crashes the request.
- Marko-side CHANGELOG (`marko/packages/core/CHANGELOG.md`):
  - Note the new `'globalMiddleware'` module.php key, the priority semantics, and the deduplication tie-breaker (app > modules > vendor; lowest priority wins within source).
  - Include an upgrade note: **"Apps that subclass `Application` and override `discoverGlobalMiddleware()` will not pick up module-declared middleware until they update their override to call `parent::discoverGlobalMiddleware()` or replicate the merge logic."**

## Requirements (Test Descriptions)

This task is documentation; no automated tests. Instead, the acceptance criteria below replace requirements.

## Acceptance Criteria
- `packages/scope/CHANGELOG.md` has a new entry under an "Unreleased" header (or whatever the project uses) summarizing the feature in 2-4 bullet points
- `packages/scope/README.md` has a complete "Scope Resolution" section covering all bullet points in the Context above
- Docs site page updated to match (consistent content; the README can be a subset)
- `marko/packages/core/CHANGELOG.md` (in the marko repo at `/home/michal/www/marko/marko/`) has an entry for module-declared global middleware
- All code examples in docs are syntactically valid PHP with `declare(strict_types=1);` shown
- All docs files pass any project linters configured for markdown (check for `markdownlint` config; ignore if absent)
- The "marko ≥ X required" note in the HTTP lifecycle section references the marko version where the change lands (use a placeholder `marko ≥ TBD` if not known at doc-write time, with a `<!-- TODO: fill in marko version after release -->` HTML comment)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
