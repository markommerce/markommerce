# Plan: Integration Testing Framework (`markommerce/testing`)

## Created
2026-06-09

## Status
completed

## Follow-up extension (added after the initial 17 tasks shipped)
Tasks 018–021 extend the framework with a full-stack **request → rendered HTML**
capability: a `BootedStore::handle(Request): Response` helper + a `storefront`
profile, then migration of the storefront feature tests off fake in-memory
repositories + hand-built router/middleware wiring onto the real harness. This
gives the "set up entities/configs → request a path → assert rendered HTML"
integration style against real data, and replaces ~600-line boilerplate
preambles in the storefront tests. (post-implementation Docker/CI work is on the
branch in commits a79e424 / 449ca34 / 0ba8947.)

## Objective
Build a first-class, in-framework integration-testing package (`markommerce/testing`) that provisions DB schema directly from entity metadata, isolates tests with per-(profile × worker) databases + transaction rollback, composes "store profiles" (module sets + scope config) for behavioral/modularity testing, and runs against a dockerized Postgres in CI. Replaces the current ad-hoc setup (8 duplicated connection helpers, drift-prone hand-written DDL, parallel races, dev-DB wipes).

## Related Issues
none

## Discovery Notes
Extended brainstorm settled the design (do NOT re-litigate). Verified against marko source (`/home/michal/www/marko/marko`) before planning:

**Reusable marko primitives (confirmed):**
- DDL is standalone in `database-pgsql/src/Sql/PgSqlGenerator.php`: `generateCreateTable(Schema\Table): string`, `generateAddIndex(string,Index): string`, `generateAddForeignKey(string,ForeignKey): string` — return SQL, no file I/O. **Use these directly; do NOT extract from MigrationGenerator.**
- `database/src/Entity/EntityDiscovery.php`: `discoverInPath(string $dir): array` (FQCNs with `#[Table]`) — point at arbitrary `<module>/src/Entity` dirs.
- `database/src/Schema/SchemaRegistry.php`: `registerEntities(array $classes)`, `getTable(name)` / get-all → `Schema\Table` (public props: name, columns[], indexes[], foreignKeys[]).
- `ConnectionInterface::execute()` runs arbitrary DDL incl. `CREATE DATABASE … TEMPLATE` (PDO autocommit, no auto-transaction wrapping). `TransactionInterface` begin/commit/rollback exist; **NO savepoints** (`nestedTransactionNotSupported`).
- `database/src/Testing/DatabaseTestHelper.php`: begin/rollback wrapper (currently unused by any test).
- Container boot (from Tier2/Tier3 manual setup, to be abstracted): build `ModuleManifest`(name,version,path,require,boot,bindings,singletons) → `PreferenceDiscovery::discoverInModule(manifest)` → `PreferenceRegistry` → `new Container($preferenceRegistry)` + `instance()/bind()/singleton()` → `DependencyResolver::resolve(manifests)` (topological, reads `->require`) → `$container->call($manifest->boot)` in order. Tier3 also wires `PluginRegistry` + `InterceptorClassGenerator`.
- Scope axes are CONFIG-driven: merged `config/scope.php` files under key `scope.axes` (via `ConfigDiscovery::discover(modulePaths, rootConfigPath)`). `locale` axis declared by `markommerce/locale`'s `config/scope.php`; `market` by `markommerce/market`. Adding axis VALUES (us/eu, en/de) = injecting into `scope.axes.<axis>.scopes` config. `PhpScopeRegistry` reads `scope.axes` in its constructor.
- Active scope set via `ScopeContext::in(axis, path)` (validates against registry); `DefaultScopeGuard::configure(defaults)` in scope boot.
- `vendor/composer/installed.json` entries have `name`, `require{}`, `extra.marko.module`, `autoload.psr-4`, `install-path` (relative from vendor/composer), `dist.type: path` (symlinked monorepo path repos). Enough to resolve package → install path → module.php + src/Entity + require[].

**Key constraints discovered:**
- **GIN index NOT expressible** via `#[Index]` (only name/columns/unique; `IndexType`=Btree/Unique/Fulltext; PgSqlGenerator emits no `USING GIN`). Resolution: config entities declare table+columns but OMIT the GIN index (it's a production perf index, irrelevant to test correctness) — keep GIN as a prod-only migration concern. Documented exception; do NOT edit marko's `#[Index]`.
- Packages are symlinked path repos; `discoverInPath` per-module is the entity→module mapping (no new metadata).
- **Composite PK NOT expressible** (devil's-advocate confirmed): `PgSqlGenerator::generateColumnDefinition` emits `PRIMARY KEY` INLINE per column (`database-pgsql/src/Sql/PgSqlGenerator.php:265-268`); two `primaryKey:true` columns → invalid double-PK DDL. Fallback (task 002): single-column entity PK + UNIQUE `#[Index]` on `(config_key, signature)`. The UNIQUE index is mandatory — `PgsqlScopedConfigStorage` upserts via `ON CONFLICT (config_key, signature)`, which needs a matching unique constraint. Prod keeps the real composite PK in the migration/emitter.
- **`DEFAULT NOW()` NOT expressible** (confirmed): `formatDefaultValue` quotes string defaults as literals → `DEFAULT 'NOW()'`. Omit DB-side `updated_at` default in entities; storage always supplies `updated_at = NOW()` in its INSERTs (confirmed in `PgsqlConfigStorage` / `PgsqlScopedConfigStorage`), so tests are unaffected. Prod default stays in migration/emitter.
- **Catalog `references` produce NO FK** (confirmed): `SchemaBuilder::buildForeignKeys` skips any `references` lacking a `.column` (`explode('.')` count check). Catalog uses `references: 'catalog_products'` (table-only) → zero FKs (matches prod, which also omits FKs). Task 004's FK requirement uses a dedicated fixture entity with a `table.column` ref instead.
- **Worker token CONFIRMED**: ParaTest (`vendor/brianium/paratest`) sets `TEST_TOKEN` (int) + `UNIQUE_TEST_TOKEN` (string) per worker (`Options.php:53-54`). Absent when non-parallel → lifecycle falls back to a single-worker token.
- **Connection-instance invariant CONFIRMED critical**: rollback only isolates if repositories AND the isolation transaction share ONE `ConnectionInterface` instance. Bootstrapper binds it via `instance()`; module.php storage bindings (config-pgsql) resolve `ConnectionInterface` from the container; lifecycle opens/rolls back the txn on that SAME object.
- **Plugin wiring is required, with ordering gotcha**: Tier3 needs `PluginRegistry`+`PluginInterceptor`+`InterceptorClassGenerator`+`setPluginInterceptor()`+`PluginDiscovery` wired BEFORE any decorated service resolves (task 006).
- **Preference-skip gotcha**: bootstrapper must skip `ConfigResolver`/`CachingConfigResolver` Preferences so config-scope's explicit factory binding wins (reproduces Tier2).

## Scope

### In Scope
- New `markommerce/testing` package (namespace `Markommerce\Testing\`), a dev/test-support library (NOT a marko module — must never boot in prod).
- `TestConnection` (single, env-driven), DB lifecycle: per-profile template DB + per-worker clone + transaction-rollback isolation + truncate opt-out.
- `SchemaProvisioner`: entity dirs → discover → SchemaRegistry → PgSqlGenerator → apply DDL.
- Config tables entity-backed (`config_values`, `config_value_overrides`) as schema source of truth (GIN omitted, prod-only).
- `ModuleResolver` (installed.json transitive resolution) + `ContainerBootstrapper` (preferences+container+boot).
- `StoreProfile` builder: `of(...)`, `simple()`, `singleMarketTwoLocales()`, `twoMarketsTwoLocales()`, `fromInstalled()`, `withMarkets/withLocale`, `boot()→BootedStore`, `inScope()`.
- `IntegrationTestCase` + Pest integration + `storeProfiles` dataset (invariant matrix).
- Fixture factory base + catalog `ProductFactory`/`CategoryFactory`.
- Migration test suite (up/down on scratch DB).
- Migrate existing integration tests onto the harness; delete 8 duplicated `PostgresTestConnection` copies; fix `CatalogSeederTreeTest` drift.
- Dockerized Postgres in CI.

### Out of Scope
- Isolated per-profile composer installs (true "package absent" via separate vendor dirs) — deferred; in-process boot-set only. A standalone "each package installs alone" check is future work.
- Editing the marko framework repo (consume only).
- Reworking the GIN index into entity metadata (kept prod-only).
- Any awareness of / dependency on a host app (e.g. the playground): markommerce packages and this test framework must be self-contained. The migration suite (012) does NOT read any app's migration files; playground migration drift is out of scope and dismissible.

## Success Criteria
- [ ] A feature test extends `IntegrationTestCase`, picks a profile, and runs in an isolated transaction with schema auto-built from entities — no hand-written DDL.
- [ ] `pest --parallel` runs the integration suite with zero cross-worker races (per-worker DBs).
- [ ] The same invariant test runs across all 3 profiles via a Pest dataset, each with a different (profile-appropriate) schema.
- [ ] `StoreProfile::fromInstalled()` boots all installed modules with the app's real scope config and provisions every installed package's entities (incl. custom).
- [ ] Config tables are created by the schema provisioner from entities (no emitter call needed in tests).
- [ ] `CatalogSeederTreeTest` passes (drift fixed); all 8 duplicated helpers deleted.
- [ ] CI runs the integration suite against Postgres (no skips).
- [ ] All tests passing; PHPStan level 8 clean; standards followed.

## Task Overview
Note: tasks are numbered in creation order, not strict execution order — the orchestrator sequences by dependencies. 008 was split into 008 (provisioning) + 016 (isolation); 013 was split into 013 (catalog+price-index migration) + 017 (Tier2/3 + helper deletion).

| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `markommerce/testing` package + autoload + test wiring | - | completed |
| 002 | Config tables entity-backed (config-pgsql, config-scope-pgsql) | - | completed |
| 003 | `TestConnection` + admin connection (CREATE/DROP/TEMPLATE DATABASE) | 001 | completed |
| 004 | `SchemaProvisioner` (entity dirs → DDL via PgSqlGenerator) | 001, 003 | completed |
| 005 | `ModuleResolver` (installed.json transitive marko-module resolution) | 001 | completed |
| 006 | `ContainerBootstrapper` (preferences + container + ordered boot + plugins) | 005 | completed |
| 007 | `StoreProfile` + builder + `BootedStore` + `inScope` + scope-config injection | 004, 006 | completed |
| 008 | DB provisioning: per-profile template + per-worker clone + teardown | 003, 004, 007 | completed |
| 016 | Per-test isolation: transaction rollback + truncate opt-out | 008 | completed |
| 009 | `IntegrationTestCase` + Pest integration + `storeProfiles` dataset | 007, 008, 016 | completed |
| 010 | Fixture factory base + catalog Product/Category factories | 009 | completed |
| 011 | Invariant-matrix + profile-specific demonstration tests | 009, 010 | completed |
| 012 | Migration generator/round-trip suite (self-contained, no app) | 003, 005 | completed |
| 013 | Migrate catalog + price-index integration tests onto harness | 009, 010 | completed |
| 017 | Migrate Tier2/Tier3 end-to-end + delete 8 dup helpers | 006, 013 | completed |
| 014 | CI: dockerized Postgres + run integration suite | 017 | completed |
| 015 | Package README + docs (per standards) | all | completed |
| 018 | `storefront` profile + injectable per-worker `ProjectPaths` base (harness plumbing) | 007 | pending |
| 019 | `BootedStore::handle()` request→HTML (RoutingBootstrapper + GlobalMiddlewareResolver + real Latte view; Vite via dev-server tags — Approach A) | 018, 009, 010 | pending |
| 020 | Migrate core storefront render tests to harness (real DB + handle(); REWRITE fake-view body assertions to real markup, keep status/header/short-circuit 1:1) | 019, 010 | pending |
| 021 | Migrate remaining storefront tests (config-driven via real config pipeline) + audit/retain fakes (expect few/no deletions) + docs | 020 | pending |

## Architecture Notes
- `markommerce/testing` is a plain composer library (NOT `extra.marko.module`), required as `require-dev`. PSR-4 `Markommerce\Testing\`.
- Schema-from-entities reuses marko `EntityDiscovery::discoverInPath` + `SchemaRegistry` + `PgSqlGenerator` directly.
- Per-profile schema = entities of the profile's booted modules only (scan each booted module's `<path>/src/Entity`).
- Isolation: per-profile Postgres template DB built once; per-worker clone via `CREATE DATABASE <profile>_<token> TEMPLATE <tmpl>`; each test wrapped in a transaction rolled back in afterEach (`DatabaseTestHelper`); `truncate` opt-out for self-committing code (no savepoints).
- Profiles compose via `of(rootPackages…)` → transitive marko-module resolution from `installed.json` → ContainerBootstrapper. Named presets + `fromInstalled()`.
- Scope values injected into `scope.axes` config before building the ConfigRepository for explicit profiles; `fromInstalled()` reads the app's real merged config.

## Risks & Mitigations
- **GIN index not expressible in entity metadata**: config entities omit it; GIN stays a prod-only migration/index concern (documented). No marko edit.
- **EntityDiscovery monorepo paths**: use `discoverInPath` against each booted module's resolved `install-path/src/Entity`; verify symlink path resolution in task 004/005.
- **CREATE DATABASE needs CREATEDB + cannot run in a transaction**: admin connection to maintenance DB, run outside any txn; require CREATEDB in dev/CI.
- **No savepoints**: truncate opt-out mode; document which tests need it (self-committing/batch paths).
- **`fromInstalled` scope config location**: auto-locating a consuming app's root config dir from a require-dev package is NOT reliable — task 007 makes `fromInstalled(string $appConfigPath)` require an explicit path (optional env-var fallback); throws clearly if unset. `ConfigDiscovery::discover(modulePaths, rootConfigPath)` signature confirmed.
- **Tier2/Tier3 migration losing coverage**: migrate behavior 1:1, keep assertions; plugin/interceptor wiring is a REQUIRED phase of ContainerBootstrapper (task 006), so the Tier migration (task 017) does not extend 006. Migration is split: task 013 (catalog + price-index, lower risk) then task 017 (Tier2 → Tier3 → helper deletions), verifying green after each.
- **Parallel template creation race**: build templates once under a Postgres session ADVISORY LOCK + `pg_database` existence check before workers clone (task 008). Template must have NO open sessions before cloning; CREATE DATABASE runs outside any transaction on a short-lived admin connection.
- **Composite PK / DEFAULT NOW() / catalog FK not expressible in entity metadata**: confirmed marko limits — task 002 uses single PK + UNIQUE index (mandatory for ON CONFLICT) and omits NOW() defaults; task 004 uses a fixture entity for the FK path. Prod keeps composite PK / NOW() / (no) FK exactly as today via migrations/emitters.
- **Connection-instance invariant**: repositories + isolation transaction must share ONE `ConnectionInterface` instance or rollback isolates nothing (tasks 006/008/009 made explicit).
- **Catalog require-dev ordering**: task 010 (first catalog code depending on testing) adds `markommerce/testing` to catalog's require-dev; task 017 adds it to the remaining packages. `markommerce/testing` never requires a markommerce module (no cycle).
- **Worker token**: ParaTest `TEST_TOKEN`/`UNIQUE_TEST_TOKEN` (confirmed); single-worker fallback when non-parallel.

**Follow-up (018–021) risks discovered in devil's-advocate review (verified against source):**
- **Vite is a real blocker, not sidestepped (018)**: the existing fake-based storefront tests bind a FAKE `ViewInterface` (`Tier1FakeView`, `CatalogSeoFakeView`, …) that emits `<div data-template=…>` placeholders to avoid `base.latte`'s `{vite()}`. The harness uses the REAL view, so `handle()` must solve Vite: either `vite.useDevServer => true` in the profile config (dev-server tags, no manifest) OR a stub manifest under the per-worker `ProjectPaths` base. Frontend's `MarkommerceLatteEngineFactory` `#[Preference]` (auto-discovered by the bootstrapper) registers the `ViteExtension`, so the real view WILL call `vite()`.
- **`ProjectPaths` not injectable (018)**: `ContainerBootstrapper::build()` hardcodes the base to `sys_get_temp_dir()/markommerce-bootstrapper-{pid}`. Layout artifacts (`var/cache/markommerce/layouts.php`) and the vite manifest (`public/build/.vite/manifest.json`) both derive from it. 018 must add a base-path hook (or rebind `ProjectPaths` in `handle()` before first render) for per-worker isolation. `CompileIfStaleMiddleware` only compiles when `APP_ENV !== 'production'`.
- **Reuse marko bootstrappers (018)**: `Marko\Routing\RoutingBootstrapper::boot($globalMiddleware)` (discovers all module controllers, registers `RouteMatcher`/`Router`) and `Marko\Core\Module\GlobalMiddlewareResolver::resolve($manifests)` (ordered `[CompileIfStale, MarkommerceLayout]`) are confirmed present; `ManifestParser` captures `globalMiddleware`. Use them instead of hand-rolling per-controller wiring.
- **Assertions can't be preserved 1:1 (019/020)**: many existing body assertions target fake-view placeholder strings (e.g. `CategoryLayoutTest`'s `toContain('catalog-storefront::components/product-grid')`). These MUST be rewritten to real markup. Status codes, the SEO `Link` header, and 404/410/302 short-circuits ARE preserved 1:1.
- **Per-test config (020)**: SEO/PresentationSwitch tests injected a fake `ConfigResolver` with per-test overrides; with the real container they must write config into the DB (or rely on package defaults). Verify the real config write path works inside the isolation transaction.
- **Fake deletion is mostly a no-op (020)**: `Fake*Repository` is referenced by ~13 unit-test files in catalog / catalog-storefront / catalog-storefront-scope that legitimately need fakes. Expect the catalog fakes to REMAIN; task 020 audits and documents rather than deletes.
