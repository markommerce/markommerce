# Task 008: DB provisioning — per-profile template + per-worker clone

**Status**: completed
**Depends on**: 003, 004, 007
**Retry count**: 0

## Description
Build the database PROVISIONING half of the lifecycle: for each store profile, build a Postgres TEMPLATE database once (schema from that profile's entity set, via `SchemaProvisioner`), clone it per parallel worker on first use (fast file copy), and drop clones on teardown. (Per-test ISOLATION — transaction rollback / truncate — is task 016, which builds on the connection this task produces.)

## Context
- Inputs: `TestConnection`/admin connection (003), `SchemaProvisioner` (004), a `StoreProfile`/`BootedStore` exposing its entity dirs + module set (007).
- Live in `packages/testing/src/Database/` (e.g. `DatabaseProvisioner.php` / `ProfileDatabase.php`).
- **Template per profile**: name e.g. `marko_test_tmpl_<profileKey>` where profileKey is a stable hash of the profile's module set. Build ONCE: admin-create empty DB → `SchemaProvisioner::provision()` its entity dirs → it becomes the template. **Parallel-race mitigation (concrete)**: wrap "create-template-if-missing" in a Postgres session-level ADVISORY LOCK on the admin connection (`SELECT pg_advisory_lock(<hash(profileKey)>)` … create-if-not-exists via `pg_database` existence check … `pg_advisory_unlock(...)`), so concurrent ParaTest workers serialize on first build and only one creates it; the rest see it exists and skip. Advisory locks are connection-scoped and released on disconnect, so a crashed worker won't deadlock others.
- **Per-worker clone**: name e.g. `marko_test_<profileKey>_<workerToken>`. **CONFIRMED worker-token mechanism**: this project's parallel runner is ParaTest (`vendor/brianium/paratest`), which sets `TEST_TOKEN` (int) and `UNIQUE_TEST_TOKEN` (string) env vars per worker (`vendor/brianium/paratest/src/Options.php:53-54`); `PARATEST=1` also set. Use `getenv('TEST_TOKEN')` as the worker id. **When running NON-parallel, these env vars are ABSENT** — fall back to a deterministic single-worker token (e.g. `'0'` or `getmypid()`); do NOT crash when the token is missing. `CREATE DATABASE <clone> TEMPLATE <tmpl>` (fast file copy). Reuse the clone across tests within a worker. Drop clones (and optionally templates) on teardown.
- **Template-clone Postgres mechanics (CONFIRMED constraints — must be honored):**
  - `CREATE DATABASE ... TEMPLATE ...` CANNOT run inside a transaction (run via the admin connection in PDO autocommit; task 003 confirms `execute()` is autocommit — ensure no surrounding txn).
  - You CANNOT clone a template while ANY session is connected to it ("source database is being accessed by other users"). After provisioning the template, CLOSE/dispose the provisioning connection (and never hold an open connection to the template) before any worker clones.
  - Use a separate, short-lived admin connection (to the maintenance DB) for the `CREATE DATABASE` statements; never issue them on a connection targeting the template/clone itself.
- **Connection handoff to isolation (CRITICAL)**: this task must produce, for a worker, ONE `ConnectionInterface` instance bound to the worker-clone DB, and expose it so that (a) task 006/007 bind that SAME object as `ConnectionInterface::class` in the container, and (b) task 016 begins/rolls back its transaction on that SAME object. Do NOT hand out a fresh connection per consumer — the single-shared-instance is what makes rollback isolation work. Expose the provisioner's discovered table-name list too (task 016 truncate mode needs it).
- Cost note: `log()`/document that templates are built lazily per profile per run; clones are cheap file copies.

## Requirements (Test Descriptions)
- [x] `it builds a profile template database once with the profile schema` (group integration-destructive)
- [x] `it clones a per-worker database from the profile template` (group integration-destructive)
- [x] `it does not rebuild the template when it already exists` (idempotent advisory-lock guard; group integration-destructive)
- [x] `it isolates two profiles into separate databases with different schemas` (simple profile lacks a market table the two-market profile has; group integration-destructive)
- [x] `it falls back to a single-worker token when not running under paratest`
- [x] `it exposes one shared connection instance bound to the worker clone database` (group integration-destructive)
- [x] `it drops worker clone databases on teardown` (group integration-destructive)

## Acceptance Criteria
- Template-per-profile + clone-per-worker working; concurrent-safe template creation (advisory lock).
- CREATE DATABASE/TEMPLATE honored (no txn, no open sessions on template).
- Exposes the single worker-clone `ConnectionInterface` instance + table list for task 016.
- Two profiles get genuinely different schemas in separate DBs.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

Implemented `DatabaseProvisioner` in `packages/testing/src/Database/DatabaseProvisioner.php`.

Key design decisions:
- **Profile key**: MD5 of sorted module names — deterministic, short, 32-char hex, safe as a DB name component.
- **Template name**: `marko_test_tmpl_<profileKey>` (stays across test runs; dropped manually when schema changes).
- **Clone name**: `marko_test_<profileKey>_<workerToken>` where token = `TEST_TOKEN` env var (ParaTest) or `'0'` (fallback).
- **Advisory lock ID**: first 8 hex chars of profileKey cast to a 31-bit signed int for `pg_advisory_lock`.
- **Provisioning connection closed before clone**: after `SchemaProvisioner::provision()`, the provisioning connection is disconnected explicitly; a separate `AdminConnection` instance is used for `CREATE DATABASE … TEMPLATE …`.
- **Single shared connection**: `connection()` always returns the same `ConnectionInterface` instance bound to the worker-clone DB; used by both container binding and isolation layer.
