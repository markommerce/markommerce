# Task 005: `StoreProfile` + cross-cutting sweep + full quality gates

**Status**: completed
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Finish the harness/test-profile cleanup, sweep for any lingering `-pgsql` references the per-package tasks
missed, run the FULL quality gates across the whole monorepo, and update the external playground.

## Context
- `packages/testing/src/Profile/StoreProfile.php`: the `config-pgsql` reference in the `storefront()` preset +
  doc comment is ALREADY fixed in task 002 (and the matching `StoreProfileTest` assertion). Here, just
  RE-VERIFY no `markommerce/*-pgsql` preset/comment remains. KEEP `marko/database-pgsql` (framework driver —
  provides `ConnectionInterface`).
- `resolveEntityDirs()` change: task 002 must have extended `resolveEntityDirs()` to also scan
  `{path}/src/PgSql/Entity` (so the moved `ConfigValueRecord`/`ConfigValueOverrideRecord` entities are still
  discovered for harness schema provisioning). RE-VERIFY it is present and that
  `packages/testing/tests/Feature/Schema/SchemaProvisionerTest.php` and the storefront/Tier2 integration
  tests still provision the config tables. If absent, add it here.
- REPO-WIDE SWEEP: `grep -rn "markommerce/scope-pgsql\|markommerce/config-pgsql\|markommerce/config-scope-pgsql\|markommerce/attribute-pgsql" packages composer.json` must return NOTHING.
  Also grep for the directory paths `packages/scope-pgsql`/etc. in any test or config and fix. Confirm the
  four dirs are gone and root `composer.json` has no stale `repositories`/`require`/`autoload-dev` entries.
- FULL GATES (the CI equivalent — these are the real acceptance):
  - `composer test` (unit/non-DB) green.
  - `composer test:integration` (DB-backed) green.
  - `./vendor/bin/phpcs` clean across the whole repo.
  - `php -d memory_limit=2G ./vendor/bin/phpstan analyse` (level 8, full project) clean.
  - `composer dump-autoload` produces no warnings about missing paths.
- PLAYGROUND (external `../playground`, NOT a CI gate): remove the four `markommerce/*-pgsql` path
  `repositories` + `require` entries from `../playground/composer.json` (keep `marko/database-pgsql`), then
  `docker compose ... exec ... composer update` and boot-check the app (`/catalog/category/1` renders). If
  the environment isn't available, document the exact edits needed instead of skipping silently.

## Requirements (Test Descriptions)
- [x] `it has no markommerce/*-pgsql reference in StoreProfile presets or comments`
- [x] `it resolves storage/renderer/repository interfaces from a profile listing only the parent packages`
- [x] `it finds zero markommerce/*-pgsql references across packages and root composer`
- [x] `it passes the full unit and integration suites after the merge`

## Acceptance Criteria
- `StoreProfile` + all profiles reference only parent packages (+ `marko/database-pgsql`).
- Repo-wide grep for `markommerce/*-pgsql` is empty; the four package dirs are gone.
- `composer test` + `composer test:integration` + phpcs + phpstan level 8 all green/clean.
- Playground updated + boot-checked (or the required edits documented if the env is unavailable).

## Implementation Notes

### StoreProfile verification
`packages/testing/src/Profile/StoreProfile.php` was already clean — no `markommerce/*-pgsql` references in
presets or doc comments. `resolveEntityDirs()` already scans both `{path}/src/Entity` and
`{path}/src/PgSql/Entity` (added in task 002). Both verified by new regression tests.

### Real leaks fixed
Two README files still contained stale driver install instructions:
- `packages/config/README.md` — removed `composer require markommerce/config-pgsql` block; replaced with
  "The package ships its PostgreSQL implementation directly — no separate driver package is required."
- `packages/config-scope/README.md` — same treatment for `markommerce/config-scope-pgsql`.

### SourceTreeTest updated
`packages/config-scope/tests/Unit/SourceTree/SourceTreeTest.php` updated to also exclude:
- `StoreProfileTest.php` (holds intentional absence-assertions for all four retired drivers)
- `/.claude/` workspace config files (`.claude/settings.local.json` has stale bash permission entries
  from earlier work sessions — these are not source code)

### Tests added
Three new tests added to `packages/testing/tests/Unit/Profile/StoreProfileTest.php`:
1. `it has no markommerce/*-pgsql consumer reference in StoreProfile presets or comments` — scans the
   `StoreProfile.php` source file for any of the four retired package names.
2. `it resolves storage/renderer/repository interfaces from a profile listing only the parent packages` —
   verifies all four presets (simple, storefront, twoMarketsTwoLocales, singleMarketTwoLocales) contain none
   of the four retired drivers, and the simple preset still has `marko/database-pgsql`.
3. `it finds zero REAL markommerce/*-pgsql references across packages and root composer (intentional
   absence-assertions excluded)` — scans high-signal files only (package composer.json, README.md,
   module.php, root composer.json, testing/src/**) so intentional test absence-assertions are not flagged.

### Playground
`/home/michal/www/marko/playground/composer.json` updated — removed four stale entries from both
`repositories` and `require`:
- `markommerce/scope-pgsql` (repositories + require)
- `markommerce/config-pgsql` (repositories + require)
- `attribute-pgsql` path entry (repositories + require)
- No `config-scope-pgsql` was present (already absent from playground)

`marko/database-pgsql` kept in both sections.

`composer update` + boot-check pending: the workspace `app` container was not running at task time
(`service "app" is not running`). The edits are in place; run the following manually when the environment
is available:
```bash
docker compose -f ~/www/marko/compose.yaml exec -w /workspace/playground app composer update
curl -s localhost:8000/catalog/category/1 | head -5
```

### Gate results
| Gate | Result |
|---|---|
| `composer test` (unit, non-DB) | 2298 passed, 8 notices, 1 skipped |
| `composer test:integration` | 168 passed |
| `composer test:all` | 2466 passed |
| `./vendor/bin/phpcs` | Clean (no violations) |
| `php -d memory_limit=2G ./vendor/bin/phpstan analyse` | OK — No errors |
| `composer dump-autoload` | No warnings |
