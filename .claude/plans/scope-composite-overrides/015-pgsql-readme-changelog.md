# Task 015: `markommerce/scope-pgsql` README + CHANGELOG + Docs Page

**Status**: pending
**Depends on**: 014
**Retry count**: 0

## Description
Update the `markommerce/scope-pgsql` README, create a `CHANGELOG.md`, and update the docs page at `docs/src/content/docs/packages/scope-pgsql.md` to describe the new query specs, the auto-emitted GIN index, the candidate cap, and the real-Postgres integration test setup.

## Context
- Files:
  - `packages/scope-pgsql/README.md` — slim README per project pattern.
  - `packages/scope-pgsql/CHANGELOG.md` — NEW.
  - `docs/src/content/docs/packages/scope-pgsql.md` — full docs.
- Content additions:
  - **Headline change**: `ScopedOrderBy` now emits a COALESCE chain over composite-signature JSONB keys (descending-score order) — replacing the old per-axis walk-up emission.
  - **Auto GIN index**: explain that `jsonb_path_ops` is added automatically via a side-channel `CREATE INDEX IF NOT EXISTS` emitter; mention the index name pattern `<table>_scopes_gin`.
  - **Candidate cap**: default 256, configurable via `SignatureCandidateEnumerator` constructor. Document the cap warning.
  - **COALESCE example** for a two-axis composite — the SQL block matches what task 011 emits.
  - **Integration tests** section: how to run them locally (`composer test:all` inside the marko-playground-app Docker container; relies on the compose Postgres).
  - **Future**: note that `ScopedSelect` / `ScopedWhere` are NOT shipped in this release because `marko/database`'s `QueryBuilderInterface` does not expose `selectRaw` / `whereRaw` yet. They are deferred to a follow-up plan.
- CHANGELOG.md `## [Unreleased]` entry:
  - **BREAKING**: `PgSqlScopeSortRenderer` removed; replaced with `PgSqlScopedFieldRenderer` implementing the new `ScopedFieldRendererInterface`.
  - **Added**: auto-emitted `jsonb_path_ops` GIN index on the `scopes` column for every table using `HasScopes` (via a side-channel `CREATE INDEX IF NOT EXISTS` emitter, since Marko's schema layer does not represent GIN types).
  - **Added**: real-Postgres integration test suite (`composer test:all`).
  - **Changed**: `ScopedOrderBy` now emits COALESCE chains over composite-signature JSONB keys in descending-score order rather than per-axis walk-up keys.
  - **Note (not shipped)**: `ScopedSelect` and `ScopedWhere` are not included — they require `selectRaw` / `whereRaw` on `marko/database`'s `QueryBuilderInterface`, which do not exist today. Deferred to a follow-up plan.

## Requirements (Test Descriptions)
- [ ] `the README mentions the auto GIN index`
- [ ] `the README links to the docs page at /docs/packages/scope-pgsql`
- [ ] `the README notes that ScopedSelect and ScopedWhere are not shipped (deferred — selectRaw/whereRaw not yet in marko/database)`
- [ ] `the CHANGELOG.md file exists and lists removal of PgSqlScopeSortRenderer`
- [ ] `the CHANGELOG.md mentions the new ScopedFieldRendererInterface`
- [ ] `the CHANGELOG.md does NOT claim ScopedSelect or ScopedWhere were added`
- [ ] `the docs page contains an ORDER BY example using ScopedOrderBy with a composite signature`
- [ ] `the docs page documents the candidate cap default of 256`
- [ ] `the docs page mentions the jsonb_path_ops GIN index by name`
- [ ] `the docs page includes the section on running integration-destructive tests`
- [ ] `the docs page notes the deferral of ScopedSelect / ScopedWhere with a brief explanation`

## Acceptance Criteria
- All requirements have passing tests (added to `packages/scope-pgsql/tests/Unit/ReadmeTest.php`).
- The COALESCE SQL example in the docs page matches the output of `PgSqlScopedFieldRenderer` (use the same fixture as task 011's tests).
- `composer test` is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
