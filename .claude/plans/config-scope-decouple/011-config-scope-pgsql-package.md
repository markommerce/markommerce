# Task 011: Scaffold and implement markommerce/config-scope-pgsql

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Create the `packages/config-scope-pgsql/` package: a Postgres driver that owns the `config_value_overrides` table and implements `ScopedConfigStorageInterface`. Scaffolding mirrors `packages/config-pgsql/` — `composer.json` requires `markommerce/config-scope` and `marko/database-pgsql`; `module.php` binds `ScopedConfigStorageInterface => PgsqlScopedConfigStorage` via a factory closure. Add `ConfigValueOverridesTableEmitter` with one CREATE TABLE statement (composite PK `(config_key, signature)`; no GIN index). Add `PgsqlScopedConfigStorage` implementing the four interface methods (loadOverrides, loadManyOverrides, saveOverride, deleteOverride) with `ON CONFLICT (config_key, signature) DO UPDATE` upserts. Include a Postgres integration test that exercises round-trip persistence.

## Context
- Related files (new):
  - `packages/config-scope-pgsql/composer.json`
  - `packages/config-scope-pgsql/module.php`
  - `packages/config-scope-pgsql/README.md`
  - `packages/config-scope-pgsql/LICENSE`
  - `packages/config-scope-pgsql/.gitattributes`
  - `packages/config-scope-pgsql/src/PgsqlScopedConfigStorage.php`
  - `packages/config-scope-pgsql/src/Schema/ConfigValueOverridesTableEmitter.php`
  - `packages/config-scope-pgsql/tests/Pest.php`
  - `packages/config-scope-pgsql/tests/PackageScaffoldingTest.php`
  - `packages/config-scope-pgsql/tests/ReadmeTest.php`
  - `packages/config-scope-pgsql/tests/Feature/ConfigValueOverridesTableEmitterTest.php`
  - `packages/config-scope-pgsql/tests/Feature/PgsqlScopedConfigStorageTest.php`
  - `packages/config-scope-pgsql/tests/Feature/Helpers/PostgresTestConnection.php` (copy or shared)
- Related (read-only):
  - `packages/config-pgsql/src/Schema/ConfigValuesTableEmitter.php` (shape reference)
  - `packages/config-pgsql/src/PgsqlConfigStorage.php` (CAS/upsert shape reference)
  - `packages/config-pgsql/tests/Feature/PgsqlConfigStorageTest.php` (test shape reference)
  - `packages/config-scope/src/Contracts/ScopedConfigStorageInterface.php`
- Patterns to follow: `config-pgsql`'s factory binding pattern in module.php (use a closure that pulls `ConnectionInterface` from the container). PostgresTestConnection helper is duplicated across packages today; copy from `catalog-market/tests/Feature/Helpers/PostgresTestConnection.php` and adapt the namespace.

## Requirements (Test Descriptions)
- [ ] `it declares its name as markommerce/config-scope-pgsql with type marko-module in composer.json`
- [ ] `it requires markommerce/config-scope and marko/database-pgsql as self.version dependencies`
- [ ] `it declares the Markommerce\\ConfigScope\\PgSql\\ namespace mapped to src/ in autoload psr-4`
- [ ] `it declares the Markommerce\\ConfigScope\\PgSql\\Tests\\ namespace mapped to tests/ in autoload-dev psr-4 (per repo convention; mirrors scope-pgsql and config-pgsql)`
- [ ] `it binds ScopedConfigStorageInterface to a PgsqlScopedConfigStorage factory in module.php`
- [ ] `it emits a CREATE TABLE config_value_overrides statement with config_key, signature, value, version, updated_at columns and a composite PRIMARY KEY (config_key, signature)`
- [ ] `it returns a list with exactly one statement from ConfigValueOverridesTableEmitter createStatements`
- [ ] `it inserts a new override row via PgsqlScopedConfigStorage saveOverride`
- [ ] `it overwrites an existing override row via PgsqlScopedConfigStorage saveOverride using ON CONFLICT DO UPDATE`
- [ ] `it loads all overrides for a given config_key as a signature=>value map via PgsqlScopedConfigStorage loadOverrides`
- [ ] `it returns an empty array from PgsqlScopedConfigStorage loadOverrides for an unknown config_key`
- [ ] `it returns multiple keys as a nested map from PgsqlScopedConfigStorage loadManyOverrides`
- [ ] `it removes a single override via PgsqlScopedConfigStorage deleteOverride leaving other overrides for the same key intact`
- [ ] `it is registered in the root composer.json require block under markommerce/config-scope-pgsql`

## Acceptance Criteria
- All requirements have passing tests (the Pgsql integration tests run under `integration-destructive` group).
- `composer validate` passes on the new package's composer.json.
- PHPStan level 8 clean for new files.
- README documents the table schema and the Tier 2 install requirement.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
