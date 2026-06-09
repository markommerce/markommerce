# Task 002: Make config tables entity-backed

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Introduce schema-declaring entities for the two non-entity config tables so the schema provisioner can create them from metadata like every other table, giving ONE schema source and killing the drift that broke the price-index rebuild. `config_values` (in `markommerce/config-pgsql`) and `config_value_overrides` (in `markommerce/config-scope-pgsql`). The entities exist purely to DECLARE schema; the runtime config storage keeps using its raw SQL.

## Context
- Replace as schema-source-of-truth: `packages/config-pgsql/src/Schema/ConfigValuesTableEmitter.php` and `packages/config-scope-pgsql/src/Schema/ConfigValueOverridesTableEmitter.php`. Keep `PgsqlConfigStorage` (raw SQL at runtime) working unchanged.
- New entities mirroring an existing entity (`packages/catalog/src/Entity/Product.php`) with `#[Table]`/`#[Column]`:
  - `config_values`: `config_key VARCHAR(255) PRIMARY KEY`, `value JSONB nullable`, `version INTEGER NOT NULL DEFAULT 0`, `updated_at TIMESTAMPTZ NOT NULL`. Place in `packages/config-pgsql/src/Entity/`.
  - `config_value_overrides`: PK on `(config_key, signature)`, `value JSONB NOT NULL`, `version INTEGER NOT NULL DEFAULT 0`, `updated_at TIMESTAMPTZ NOT NULL`. Place in `packages/config-scope-pgsql/src/Entity/`.
- **CONFIRMED HARD LIMIT — composite PK is NOT expressible** via marko entity metadata. `PgSqlGenerator::generateColumnDefinition()` (database-pgsql `src/Sql/PgSqlGenerator.php:265-268`) emits `PRIMARY KEY` INLINE per column; two columns marked `primaryKey: true` would produce two inline `PRIMARY KEY` constraints, which Postgres rejects ("multiple primary keys for table are not allowed"). There is NO table-level composite-PK emission path. **Required fallback (do not re-investigate):** for `config_value_overrides`, mark exactly ONE column as the entity PK (e.g. an added surrogate, OR mark `config_key` PK) and express the real `(config_key, signature)` uniqueness via a `#[Index(unique: true, columns: ['config_key','signature'])]`. The PRODUCTION composite PK stays in the prod migration / `ConfigValueOverridesTableEmitter` (unchanged). For test correctness the unique index is sufficient. **The index on `(config_key, signature)` MUST be UNIQUE** — `PgsqlScopedConfigStorage` writes via `INSERT ... ON CONFLICT (config_key, signature) DO UPDATE` (`packages/config-scope-pgsql/src/PgsqlScopedConfigStorage.php:89-95`), and Postgres requires a unique constraint/index matching that conflict target or the write fails at runtime. Likewise `config_values` needs a unique/PK on `config_key` (single-column PK already covers `ON CONFLICT (config_key)`). If a surrogate column is undesirable, mark `config_key` as the single entity PK + the unique index on the pair — the provisioned test table will still satisfy `PgsqlScopedConfigStorage`'s INSERT/SELECT by `(config_key, signature)`. Document the deliberate divergence from prod in a code comment.
- **CONFIRMED HARD LIMIT — `DEFAULT NOW()` is NOT expressible.** `PgSqlGenerator::formatDefaultValue()` (same file, line 307-326) quotes string defaults as string literals, so `#[Column(default: 'NOW()')]` would emit `DEFAULT 'NOW()'` (a literal string), not the function call. **Do NOT put `NOW()` defaults on the entity.** Omit the DB-side default on `updated_at`; `PgsqlConfigStorage`/`PgsqlScopedConfigStorage` already supply `updated_at` on every write (verify they pass it explicitly — if any code path relied on the DB default, fix the write to set it), so tests are unaffected. The prod `DEFAULT NOW()` stays in the migration/emitter. Document this divergence in a code comment too.
- **GIN index is NOT expressible** via marko's `#[Index]` (name/columns/unique only; no `USING GIN`). The playground `config_values` migration has a GIN index on the JSONB `value`. Resolution (agreed): the entity OMITS the GIN index — it's a production performance index, irrelevant to test correctness. Keep the GIN index as a prod-only concern (leave it in the prod migration / emitter path). Add a clear code comment documenting this deliberate omission.
- Composite PK: confirm marko's `#[Column(primaryKey: true)]` supports two columns marked primary (check `EntityMetadataFactory` / an existing composite-PK entity if any). If a composite PK can't be expressed, document and use the closest expressible form that still lets the provisioner create a usable table (e.g. unique index on the pair) — note it for the devil's-advocate.
- These packages may currently have NO `src/Entity` dir — creating one is fine; `EntityDiscovery::discoverInPath` will pick it up (task 004).
- The default/runtime `TIMESTAMPTZ DEFAULT NOW()` must survive: verify `#[Column(default: ...)]` or the generated DDL keeps the default; if marko can't emit `DEFAULT NOW()`, note it (tests can tolerate, prod relies on the migration).

## Requirements (Test Descriptions)
- [x] `it declares the config_values table via entity metadata`
- [x] `it declares config_values columns config_key value version and updated_at`
- [x] `it marks config_key as the primary key on config_values`
- [x] `it declares the config_value_overrides table via entity metadata`
- [x] `it declares a unique index on config_key and signature for the overrides table` (matches the storage ON CONFLICT target)
- [x] `it keeps the existing config storage tests passing` (run config-pgsql + config-scope-pgsql existing suites; they must stay green)
- [x] `it documents the intentionally omitted GIN index in a code comment`

## Acceptance Criteria
- Both entities exist with `#[Table]`/`#[Column]`, discoverable via `EntityDiscovery`.
- `PgsqlConfigStorage` and `config-scope-pgsql` storage runtime behavior unchanged; their existing tests pass.
- GIN omission documented in code.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes

- Created `packages/config-pgsql/src/Entity/ConfigValueRecord.php`: `#[Table('config_values')]` with `config_key` as primary key (VARCHAR 255), `value` JSONB nullable, `version` INTEGER NOT NULL, `updated_at` TIMESTAMPTZ NOT NULL. GIN index intentionally omitted (comment in entity). `updated_at` and `version` defaults omitted (Marko can't emit `DEFAULT NOW()`/`DEFAULT 0`; storage supplies values at write time).
- Created `packages/config-scope-pgsql/src/Entity/ConfigValueOverrideRecord.php`: `#[Table('config_value_overrides')]` with surrogate autoincrement `id` PK (Marko can't express composite PKs), columns `config_key`, `signature`, `value` JSONB NOT NULL, `version`, `updated_at`. `#[Index(name: 'uniq_config_value_overrides_key_signature', columns: ['config_key', 'signature'], unique: true)]` satisfies the `ON CONFLICT (config_key, signature)` upsert in `PgsqlScopedConfigStorage`.
- Tests: `packages/config-pgsql/tests/Unit/Entity/ConfigValueRecordTest.php`, `packages/config-scope-pgsql/tests/Unit/Entity/ConfigValueOverrideRecordTest.php`, `packages/config-pgsql/tests/Unit/StorageUnaffectedByEntityTest.php`.
- PHPStan level 8 clean. Runtime storage classes unchanged.
