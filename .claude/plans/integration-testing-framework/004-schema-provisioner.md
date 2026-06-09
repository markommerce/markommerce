# Task 004: `SchemaProvisioner` — entity dirs → DDL

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Build the core schema-from-entities engine: given a set of entity directories (each booted module's `src/Entity`), discover the `#[Table]` entities, build `Schema\Table` objects, generate CREATE TABLE / index / FK DDL via marko's standalone `PgSqlGenerator`, and apply it to a target connection. No migration files. This is what makes test schema drift-proof and per-profile.

## Context
- Reuse VERIFIED marko APIs:
  - `Marko\Database\Entity\EntityDiscovery::discoverInPath(string $dir): array` (FQCNs with `#[Table]`). Construct with its `ClassFileParser` dep.
  - `Marko\Database\Schema\SchemaRegistry` (ctor: `EntityMetadataFactory`, `SchemaBuilder`): `registerEntities(array $classes)`, then retrieve `Schema\Table` objects (use the get-all/`getTable` API — confirm exact method when implementing).
  - `Marko\Database\PgSql\Sql\PgSqlGenerator` (in `database-pgsql/src/Sql/`): `generateCreateTable(Schema\Table): string`, `generateAddIndex(string $table, Schema\Index): string`, `generateAddForeignKey(string $table, Schema\ForeignKey): string`. Returns SQL strings.
- Apply order matters: create all tables first, then indexes, then foreign keys (FKs reference other tables). The DiffCalculator/MigrationGenerator topologically sorts by FK deps — replicate "tables before FKs" ordering (creating all tables first then adding FKs avoids ordering issues entirely).
- **CONFIRMED GOTCHA — catalog entities generate NO foreign keys.** `SchemaBuilder::buildForeignKeys()` (marko `database/src/Entity/SchemaBuilder.php:99-132`) parses `#[Column(references: ...)]` as `"table.column"` via `explode('.')` and SILENTLY SKIPS any reference lacking a `.column` (`count($parts) !== 2`). The real catalog entities use `references: 'catalog_products'` (table only, no column) — so they produce ZERO FK constraints (matching prod, where the migrations also omit FKs — only a unique index exists on `catalog_product_category`). Therefore: (a) do NOT assert FKs against catalog entities; the "FK" requirement below must use a DEDICATED FIXTURE entity in `packages/testing/tests/` that declares `references: 'other_table.id'` (proper `table.column` form) to exercise the FK path. (b) The provisioner still creates the catalog tables + the `uniq_catalog_product_category` UNIQUE index correctly — that is the real catalog assertion.
- Live in `packages/testing/src/Schema/SchemaProvisioner.php`. API roughly: `provision(ConnectionInterface $conn, array $entityDirs): void` (discover across dirs, dedupe FQCNs, register, emit, apply). Also a way to list the resulting table names (for truncation/template introspection later).
- **Monorepo path caveat**: packages are symlinked path repos; `discoverInPath` must resolve real `src/Entity` dirs. Verify discovery works against an absolute `packages/<pkg>/src/Entity` path AND against the symlinked `vendor` path (task 005/007 will feed resolved install-paths).
- Idempotency: prefer `DROP …`-free creation into a FRESH database (the lifecycle clones empty DBs), but a `IF NOT EXISTS`-tolerant or drop-first option is useful for the template build. Keep the provisioner focused on "apply schema to an empty DB"; lifecycle (008) owns DB creation.

## Requirements (Test Descriptions)
- [x] `it discovers table entities across multiple entity directories`
- [x] `it creates all discovered tables in a fresh database` (group integration-destructive)
- [x] `it creates columns matching the entity metadata` (introspect a created table; group integration-destructive)
- [x] `it creates foreign keys after all tables exist using a fixture entity with a table-dot-column reference` (FK path needs `references: 'table.column'`; catalog entities use table-only refs that SchemaBuilder skips; group integration-destructive)
- [x] `it creates declared indexes including the catalog product-category unique index` (group integration-destructive)
- [x] `it provisions the catalog product and category tables from the catalog entity dir` (group integration-destructive)
- [x] `it deduplicates entities discovered from overlapping directories`
- [x] `it exposes the provisioned table names`

## Acceptance Criteria
- `SchemaProvisioner` builds + applies schema for a given set of entity dirs onto a connection, FKs last.
- Verified against the real catalog entities (products/categories/assignment) producing usable tables.
- Exposes discovered table names for downstream truncation.
- PHPStan level 8 clean (run with `php -d memory_limit=2G`).

## Implementation Notes
- `SchemaProvisioner` lives in `packages/testing/src/Schema/SchemaProvisioner.php`
- Fixture entities for FK testing live in `packages/testing/tests/Fixture/Entity/` (`ParentFixtureEntity`, `ChildFixtureEntity`) with proper `references: 'fixture_parents.id'`
- Deduplication uses a hash map (`$entityClasses[$fqcn] = true`) — naturally handles overlapping directories
- `tableNames(array $entityDirs)` builds a registry and returns `getTableNames()` — usable without a DB connection
- Apply order in `provision()`: CREATE TABLE for all tables, then CREATE INDEX for all, then ADD FOREIGN KEY for all (avoids FK ordering issues)
- PHPStan level 8 clean; phpcs + php-cs-fixer both pass
