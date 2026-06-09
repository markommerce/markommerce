# Task 014: CI — dockerized Postgres + run integration suite

**Status**: completed
**Depends on**: 017
**Retry count**: 0

## Description
Make the integration suite actually RUN in CI (today it skips when `DB_*` env is absent, so it only runs on dev machines). Add a CI job with a Postgres service, the required env, CREATEDB privilege, and a command that runs the integration-destructive suite (incl. the profile matrix) against it.

## Context
- Determine the CI system in use: check for `.github/workflows/`, `.gitlab-ci.yml`, or similar at repo root; if none exists, add a GitHub Actions workflow (most likely) — confirm by inspecting the repo. Mirror any existing workflow's PHP/composer setup (PHP 8.5).
- Provide a Postgres service (container) with a user that has CREATEDB (the lifecycle creates per-worker template/clone DBs). Set `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` (+ `DB_ADMIN_DATABASE` if task 003 added it) as env for the test step.
- Run command: the integration tests are in the `integration-destructive` group. Add/confirm a composer script — e.g. `composer test:integration` (runs `pest --group=integration-destructive`, parallel) or wire `composer test:all`. Match the docker/compose conventions in CLAUDE.local.md if CI reuses the compose file; otherwise run pest directly with the service.
- Ensure the unit suite (`composer test`, excludes integration-destructive) still runs as its own fast job, and the integration job runs separately so a missing DB never silently skips in CI.
- PHPStan in CI must use `php -d memory_limit=2G` (the env's default 128M OOMs).

## Requirements (Test Descriptions)
(CI config is validated by the pipeline running, not Pest tests. Track outcomes as checkboxes; add a tiny smoke assertion where feasible.)
- [x] `it defines a CI job with a postgres service and DB env`
- [x] `it grants the CI database user createdb privilege`
- [x] `it runs the integration-destructive suite in CI (not skipped)`
- [x] `it runs the unit suite as a separate fast job`
- [x] `it runs phpstan with raised memory in CI`

## Acceptance Criteria
- CI runs the integration suite against a real Postgres; the profile-matrix tests execute (not skipped).
- Unit and integration suites are separate jobs; PHPStan runs with 2G.
- A `composer test:integration` (or equivalent) script exists and is used by CI.

## Implementation Notes

- Added `test:integration` script to root `composer.json`: `pest -c phpunit.xml --parallel --group=integration-destructive`
- Created `.github/workflows/ci.yml` with three jobs: `unit` (no DB), `static` (PHPStan with 2G memory), `integration` (postgres:16-alpine service, `postgres` superuser has CREATEDB by default)
- Integration job env: `DB_HOST=127.0.0.1`, standard GH Actions service port-mapping pattern
- Smoke tests added in `packages/testing/tests/Unit/CiWorkflowTest.php` (string-based YAML assertions, no YAML parser dependency)
- Verified locally: `composer test:integration` runs 199 integration tests green against dev Postgres
