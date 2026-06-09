# Task 015: `markommerce/testing` package README + docs

**Status**: completed
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 016, 017
**Retry count**: 0

## Description
Write the package `README.md` (per the project's Package README Standards in `code-standards.md` / `docs/DOCS-STANDARDS.md`) and a docs-site page documenting how to use `markommerce/testing` — for both internal contributors and merchant developers. Final task so docs reflect what was actually built.

## Context
- `packages/testing/README.md` following the slim-README standard (other package READMEs are pointers to the docs site — match that convention).
- Docs page `docs/src/content/docs/packages/testing.md` covering:
  - Quick start: extend `IntegrationTestCase`, pick a profile, write a factory-driven test.
  - The three named profiles + when to use each; how to author a custom `of(...)` profile.
  - **Merchant usage**: `StoreProfile::fromInstalled()` — auto-derives their store from composer.json + their real scope config; custom `#[Table]` entities auto-provisioned; how to write factories for custom entities over the base.
  - Isolation model: per-(profile×worker) DB, transaction rollback, the `truncate` opt-out and when to use it.
  - The `storeProfiles` dataset for invariant-matrix tests, with the example from task 011.
  - Schema-from-entities (no migrations needed for tests); the separate migration suite; the documented GIN-index omission.
  - Running locally (DB env vars, docker) and in CI.
- Reference the canonical example test from task 011.

## Requirements (Test Descriptions)
- [x] `it documents the quick-start for writing an integration test`
- [x] `it documents the three named store profiles`
- [x] `it documents fromInstalled for merchant developers`
- [x] `it documents the isolation model and truncate opt-out`
- [x] `it documents the storeProfiles invariant-matrix dataset`
- [x] `it follows the package README standard`

(If the repo has a README/docs lint or `ReadmeTest` pattern like other packages, satisfy it; otherwise these are content-presence checks.)

## Acceptance Criteria
- README + docs page exist, accurate to the built API, following DOCS-STANDARDS.
- Covers internal + merchant usage, isolation, profiles, schema, CI.
- Any package `ReadmeTest`/docs lint passes.

## Implementation Notes

- Created `packages/testing/tests/Unit/ReadmeTest.php` with 6 content-presence tests checking both the README and docs page.
- Updated `packages/testing/README.md` to the slim format (title, installation, quick example, documentation link).
- Created `docs/src/content/docs/packages/testing.md` with full package docs covering all required topics: quick start, three named profiles, custom profiles, `fromInstalled` merchant usage, isolation model (rollback + truncate), `storeProfiles` dataset, schema-from-entities, GIN index omission, migration round-trip suite, local and CI running instructions.
