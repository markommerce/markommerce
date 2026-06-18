# Plan: Retire the `-pgsql` driver packages

## Created
2026-06-18

## Status
completed

## Objective
Collapse the four markommerce `-pgsql` driver packages back into their parent packages. Markommerce
commits to PostgreSQL permanently, so the per-domain "swappable DB driver" split is dead weight — each
`{domain}-pgsql` merges into `{domain}`, leaving one package per domain that ships its Postgres
implementation directly.

## Related Issues
none

## Discovery Notes
Verified against the four packages. Each `-pgsql` package is a marko module whose driver classes ALREADY
live under the parent's namespace (`Markommerce\{Domain}\PgSql\…`), so merging needs **no class renames** —
just moving files under `packages/{domain}/src/PgSql/`, folding the `module.php` binding into the parent's
existing `module.php`, bumping the parent dep `marko/database` → `marko/database-pgsql`, deleting the
`-pgsql` dir, and fixing reference sites.

The four merges:
| Driver pkg | → Parent | Driver src | Binding folded |
|---|---|---|---|
| `scope-pgsql` | `scope` | `Query/PgSqlScopedFieldRenderer`, `Schema/ScopesGinIndexEmitter` | `ScopedFieldRendererInterface` → `PgSqlScopedFieldRenderer` |
| `config-pgsql` | `config` | `PgsqlConfigStorage`, `Entity/ConfigValueRecord`, `Schema/ConfigValuesTableEmitter` | `ConfigStorageInterface` → closure(`PgsqlConfigStorage`) |
| `config-scope-pgsql` | `config-scope` | `PgsqlScopedConfigStorage`, `Entity/ConfigValueOverrideRecord`, `Schema/ConfigValueOverridesTableEmitter` | `ScopedConfigStorageInterface` → closure(`PgsqlScopedConfigStorage`) |
| `attribute-pgsql` | `attribute` | `PgSqlAttributeDefinitionRepository`, `PgSqlAttributeOptionRepository` | `AttributeDefinitionRepositoryInterface` → `PgSqlAttributeDefinitionRepository` |

**Framework split untouched:** `marko/database` (abstractions) + `marko/database-pgsql` (the *framework's*
pgsql driver) stay as real deps. We only collapse markommerce's per-domain `-pgsql` packages.

**Semantic ripple (the non-mechanical part) — CORRECTED against real code:**
- `scope` ships a **`NoDriverException` + `DRIVER_PACKAGES`** class — BUT verified, `noDriverInstalled()` is
  **never called anywhere in scope's `src/`** (no throw site; `ScopedFieldRendererInterface` resolution does
  not route through it). It is already dead code that exists only to satisfy tests. So "retire the guard" =
  DELETE `NoDriverException.php` + remove every test reference. FOUR test files reference it (not three):
  `scope/tests/Unit/SourceTree/SourceTreeTest`, `Unit/ModulePhpTest`, `Unit/ReadmeTest`, AND
  `Unit/Query/ScopedFieldExpressionTest` (previously missed). Note `ModulePhpTest`'s `does not bind
  ScopedFieldRendererInterface` assertion INVERTS after merge.
- `config` does NOT have a `ModulePhpTest` assertion that it "does not bind ConfigStorageInterface" — verified.
  It only has a CODE COMMENT + a manual `InMemoryConfigStorage` test-double bind. So there is no assertion to
  invert; just update the comment and ADD a positive binding assertion. Neither `config` nor `config-scope`
  has any NoDriver-style runtime guard (verified — nothing to retire there).
- `attribute-pgsql` has NO `AttributeOptionRepositoryInterface` to bind — `PgSqlAttributeOptionRepository` is
  instantiated directly inside `PgSqlAttributeDefinitionRepository`, not via the container. Only the
  definition-repo binding folds in.

**Reference sites (verified, modest):**
- Root `composer.json`: 4 `require` + 4 path-`repositories` + 1 `autoload-dev.psr-4` (attribute) entries.
- `packages/testing/src/Profile/StoreProfile.php`: presets list `markommerce/config-pgsql`; KEEP
  `marko/database-pgsql` (framework driver provides the `ConnectionInterface`).
- Cross-package Feature tests building `StoreProfile::of(...)` with `markommerce/{domain}-pgsql` — grouped by
  driver: `attribute-pgsql` in `catalog-attribute*`/`catalog-attribute-index`/`-scope`/`-storefront`;
  `config-pgsql` in `catalog-storefront/Tier1CompileTest`, `config-scope/Tier2EndToEndTest`,
  `catalog-attribute-storefront/CategoryPageRenderTest`; `config-scope-pgsql` in `config-scope/Tier2EndToEndTest`.
- `config`/`catalog` `ComposerManifestTest` assertions referencing the `-pgsql` names (some invert).
- `FEATURES.md` (already refreshed to 41 packages on this branch — delta only).

## Scope

### In Scope
- Merge all four `-pgsql` packages into their parents (src + tests + module.php binding + composer dep bump + delete dir).
- Retire scope's no-driver guard (and config's storage-from-driver assumption) now that the driver ships in-package.
- Update all reference sites: root composer, `StoreProfile`, cross-package Feature-test profile lists, manifest/module tests.
- Update `FEATURES.md` (41 → 37, drop the 4 rows + the driver-naming convention bullet) and parent READMEs/docs.
- Keep the playground note (external repo; verify + update its composer + boot-check, not blocking CI).

### Out of Scope
- Any change to the marko FRAMEWORK (`marko/database`, `marko/database-pgsql` stay).
- Behavior changes to the storage/repository logic itself (pure relocation).
- Collapsing any non-`-pgsql` package or introducing a `cart` package (separate effort, next).
- A second DB backend (explicitly abandoned — that's the whole point).

## Success Criteria
- [ ] The four `packages/*-pgsql/` directories no longer exist; their code lives under the parent packages.
- [ ] Each parent package binds its own storage/renderer/repository via its `module.php`; resolving the relevant interface needs no separate `-pgsql` package.
- [ ] scope's no-driver guard is retired (or the driver is always bound) and no test references a `markommerce/*-pgsql` package.
- [ ] Root composer, `StoreProfile`, and all Feature-test profiles reference only the parent packages (+ the framework's `marko/database-pgsql`).
- [ ] `FEATURES.md` reflects 37 packages with no `-pgsql` rows.
- [ ] Full Pest suite (unit + integration) green; phpcs clean; phpstan level 8 clean.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Merge `scope-pgsql` → `scope` (+ DELETE dead NoDriverException, fix 4 test files incl. ScopedFieldExpressionTest) | - | completed |
| 002 | Merge `config-pgsql` → `config` (+ extend `resolveEntityDirs()` to scan `src/PgSql/Entity`; fix StoreProfile + StoreProfileTest + ContainerBootstrapperTest + Tier1/Tier2) | 001 | completed |
| 003 | Merge `config-scope-pgsql` → `config-scope` (relies on 002's `resolveEntityDirs` change; invert ComposerManifestTest require + autoload-dev assertions) | 002 | completed |
| 004 | Merge `attribute-pgsql` → `attribute` (no option-repo binding; repoint root autoload-dev to `tests/PgSql/` subdir) | 003 | completed |
| 005 | StoreProfile re-verify + entity-dir re-verify + cross-cutting sweep + full quality gates | 001, 002, 003, 004 | completed |
| 006 | `FEATURES.md` + parent READMEs/docs (+ remove dead docs/*-pgsql.md pages) | 001, 002, 003, 004, 005 | completed |

## Architecture Notes
- **Serialized merges (001→002→003→004).** Each task edits the shared root `composer.json` (and some shared
  Feature tests), so they run sequentially to avoid write contention between parallel workers. Each task
  leaves the suite green and `develop` shippable on its own.
- **Per-package recipe:** move `packages/{domain}-pgsql/src/*` → `packages/{domain}/src/PgSql/*` (namespace
  unchanged); move meaningful tests → `packages/{domain}/tests/PgSql/...` (keep `Markommerce\{Domain}\PgSql\Tests\`),
  DROP the `-pgsql` package's scaffolding/meta tests (PackageScaffoldingTest, SourceTree/CopiedTestTree/
  AutoMigration/Module/Readme that assert the now-deleted package's existence); fold the binding into the
  parent `module.php`; bump parent composer `marko/database` → `marko/database-pgsql` (+ any extra dep);
  remove the `-pgsql` root composer `require`/`repositories` entries; ADD a root `autoload-dev.psr-4` entry
  `"Markommerce\\{Domain}\\PgSql\\Tests\\": "packages/{domain}/tests/PgSql/"` (for `attribute` this means
  REPOINTING the existing entry to the `/PgSql/` subdir, not deleting it); delete the dir; `composer dump-autoload`.
- **AUTOLOAD GOTCHA (VERIFIED):** test files keep the `Markommerce\{Domain}\PgSql\Tests\` namespace, which is
  NOT covered by the parent's `Markommerce\{Domain}\Tests\` → `tests/` PSR-4 prefix. Only `attribute-pgsql`
  currently has a ROOT `autoload-dev` entry; the other three rely on Pest's directory scan and have none. To
  be safe and uniform, each task ADDS a root `/PgSql/`-subdir autoload-dev entry and lands files there. Do NOT
  point `Markommerce\{Domain}\PgSql\Tests\` at the parent `tests/` root — it overlaps the `…\Tests\` prefix and
  can mis-resolve.
- **ENTITY-DISCOVERY GOTCHA (VERIFIED, CRITICAL):** the testing harness
  (`StoreProfile::resolveEntityDirs()` → `SchemaProvisioner`) discovers entities ONLY under
  `{packagePath}/src/Entity`. `config`/`config-scope` ship DB entities (`ConfigValueRecord`,
  `ConfigValueOverrideRecord`) that the recipe moves to `src/PgSql/Entity` — which the harness then can't see,
  breaking provisioning of `config_values`/`config_value_overrides` for harness-booted integration tests
  (e.g. the `storefront()` profile, Tier2 manual-emitter tests are unaffected). FIX: task 002 extends
  `resolveEntityDirs()` to also scan `{path}/src/PgSql/Entity`. (attribute's entities already live in the
  parent `attribute/src/Entity` and don't move — attribute is unaffected.)
- **The schema emitters** (`ScopesGinIndexEmitter`, `ConfigValuesTableEmitter`,
  `ConfigValueOverridesTableEmitter`) are NOT discovered by any registry/convention — they are plain classes
  invoked directly by tests (and the production migration path). Moving them under `src/PgSql/Schema/` keeps
  their FQCN unchanged (namespaces preserved), so every direct reference still resolves. No discovery wiring
  to fix for the emitters; the discovery risk is ONLY the entities above.
- **Parent packages are already marko modules** (`extra.marko.module: true` + a `module.php`), so the folded
  binding is discovered with no new wiring — verify per package.
- Standards unchanged: PHP 8.5, strict types, no `final`, constructor injection, `@throws`, phpstan level 8.

## Risks & Mitigations
- **scope no-driver guard (highest risk):** retiring `NoDriverException`/`DRIVER_PACKAGES` touches scope core
  + 3 tests. Mitigation: task 001 owns it end-to-end; if removal is too invasive, fall back to always
  binding the renderer in scope's `module.php` and neutralizing the guard. devils-advocate to scrutinize.
- **Half-updated composer breaks the suite mid-task:** each merge task updates the parent composer AND root
  composer AND deletes the dir AND runs `composer dump-autoload` within the one task, so the repo is never
  left referencing a deleted path repo. Serialization prevents concurrent root-composer edits.
- **Inverting manifest-test assertions:** `config`/`catalog` `ComposerManifestTest` + `config` `ModulePhpTest`
  encode the old split as invariants; the merge tasks must flip the specific assertions (don't just delete).
- **Playground (external repo):** can't be fixed in-repo; task 005 updates `../playground/composer.json`,
  runs `composer update`, and boot-checks the app, but its state is not a CI gate.
- **`self.version` deps:** the `-pgsql` packages require `marko/database-pgsql: self.version`; ensure the
  parent uses the same constraint style its siblings use so monorepo resolution stays intact.
- **`marko/database` → `marko/database-pgsql` is a REPLACE, not an ADD (VERIFIED safe):** scope/config/attribute
  src use `Marko\Database\*` abstractions (from `marko/database`). Replacing the dep with `marko/database-pgsql`
  is safe because `marko/database-pgsql` requires `marko/database` transitively — proven by the existing
  `-pgsql` packages, which require ONLY `marko/database-pgsql` yet use `Marko\Database\Repository\Repository`
  etc. `config-scope` currently has NO `marko/database` dep at all, so for it this is a pure ADD of
  `marko/database-pgsql: self.version` (task 003).
