# Plan: Decouple config from scope (Phase 5)

## Created
2026-05-27

## Status
completed

## Objective
Remove all scope coupling from `markommerce/config` and `markommerce/config-pgsql` so that a Tier 1 corner-shop merchant can install both packages with no scope/locale/market machinery, and introduce four new packages (`config-scope`, `config-scope-pgsql`, `config-locale`, `config-market`) that compose the per-scope override resolution catalog ships today. After this plan, the final phase of the FEATURES.md refactor is complete: `config` becomes a plain key/value store; per-scope overlays come from `config-scope` + its bridges.

## Related Issues
none

## Discovery Notes

### Phase 1–4 foundation already in place
- `ScopedFieldRegistry` (shipped in P1) is the authoritative source for which fields are scoped by which axes. Boot-time `module.php` closures call `register()` and consumers read from it.
- `markommerce/locale` (P2) and `markommerce/market` (P4) ship as axis-declaration-only packages — pure `config/scope.php` files contributing `axes.locale` / `axes.market`.
- The bridge pattern (`catalog-locale`, `catalog-market`) is established: a `module.php` with a `require` block listing the two packages it depends on, and a `boot` closure that registers `(entityClass, property, axes)` triples with `ScopedFieldRegistry`. `catalog-market` ships as a no-op bridge (placeholder) for entries that don't have a real consumer yet.
- The Preference-based behavior swap (`ScopedProductGridComponent extends ProductGridComponent`, `#[Preference(replaces: ProductGridComponent::class)]`) is the proven pattern for Tier 1 → Tier 2 behavior upgrade.

### Pre-P5 scope-coupling surface in config and config-pgsql
Auditing `grep -rn "Markommerce\\Scope\\|markommerce/scope" packages/config packages/config-pgsql`:

Production code (`packages/config/src/`):
- `ConfigResolver.php` — injects `ScopeContext`, calls `OverrideMatcher::match(row, axes, context)` from `resolvedAt()`. The `resolved()` method delegates to `resolvedAt()` using the injected live context.
- `ConfigWriter.php` — `setOverride(string $key, ScopeSignature $signature, mixed $value)` and `unsetOverride()` take a `Markommerce\Scope\Signature\ScopeSignature`; validates that the signature's axes are declared on the property via `$definition->axes`.
- `Contracts/ConfigWriterInterface.php` — interface declares `setOverride`/`unsetOverride` with a `ScopeSignature` parameter.
- `Cache/CachingConfigResolver.php` — injects `ScopeContext`; cache-key builder reads `$definition->axes` and walks `$context->get($axis)` to produce a deterministic per-context key.
- `Registry/ConfigRegistryBuilder.php` — injects `ScopeRegistryInterface`, reads `#[Scoped]` attribute from config-class properties, validates each axis against the registry, populates `ConfigDefinition::axes`.
- `Resolution/OverrideMatcher.php` — uses `SignatureCandidateEnumerator` from scope; matches stored override-signature strings against the candidate list produced by the registry's hierarchy.
- `Command/SetCommand.php`, `Command/UnsetCommand.php` — parse `--scope=axis=value,…` strings into `ScopeSignature` and call `writer->setOverride(...)` / `unsetOverride(...)`.
- `Command/ConfigGetCommand.php` — injects `ScopeRegistryInterface`, parses `--scope=…` into a synthetic `ScopeContext`, constructs an `OverrideMatcher` + `SignatureCandidateEnumerator` ad-hoc to resolve via `resolvedAt()`.
- `Exceptions/AxisNotDeclaredException.php` — thrown only by `ConfigWriter` (signature-axis check) and `ConfigRegistryBuilder` (build-time check).
- `module.php` — imports `ScopeContext`, `ScopeRegistryInterface`; the `ConfigResolver` factory wires `ScopeContext` in. `ConfigRegistryBuilder` boot path passes `ScopeRegistryInterface`.

Value objects (`packages/config/src/ValueObjects/`):
- `ConfigDefinition` carries a `public list<string> $axes` field.
- `ConfigRow` carries a `public array<string, mixed> $overrides` map, plus `withOverride()` / `withoutOverride()` factory methods.

Storage (`packages/config/src/Storage/InMemoryConfigStorage.php`):
- `compareAndSave()` treats `value === null && overrides === []` as "empty row" (deletes).
- The constructor and hydrate path persist the `overrides` map alongside the global value.

Driver (`packages/config-pgsql/`):
- `src/Schema/ConfigValuesTableEmitter.php` — emits `overrides JSONB NOT NULL DEFAULT '{}'::jsonb` column and a GIN index `config_values_overrides_gin` on it.
- `src/PgsqlConfigStorage.php` — INSERT/UPDATE statements always read and write the `overrides` column.

Tests (`packages/config/tests/`):
- `Fakes/FakeScopeRegistry.php` exists solely to construct registries / contexts.
- `Unit/ConfigResolverTest.php`, `Unit/ConfigWriterTest.php`, `Unit/SecretCipherIntegrationTest.php`, `Unit/Cache/CachingConfigResolverTest.php`, `Unit/Resolution/OverrideMatcherTest.php`, `Unit/Registry/ConfigRegistryBuilderTest.php`, `Unit/Resolver/ConfigResolverGetTest.php`, `Unit/Command/{SetCommand,UnsetCommand,ConfigGetCommand,ConfigListCommand}Test.php`, `Feature/ModulePhpTest.php`, `PackageScaffoldingTest.php` all import from `Markommerce\Scope\…`.
- `Unit/ValueObjects/ConfigRowTest.php` and `ConfigDefinitionTest.php` exercise the `overrides`/`axes` shape directly.

External consumers of config's scope-aware API: **none**. `grep -rln "ConfigResolver\\|markommerce/config" packages/` outside config/config-pgsql finds only `catalog-storefront/tests/Feature/Tier1EndToEndTest.php`, which boots `markommerce/config` + `markommerce/config-pgsql` as modules but never calls `setOverride` or reads scope-aware values. Demo packages do not consume config at all.

### Decisions locked in during clarification
- **Override storage:** new `markommerce/config-scope-pgsql` package owns a separate `config_value_overrides` table. `config-pgsql` (Tier 1) stops emitting the `overrides` JSONB column and GIN index from `config_values`. Cleanest interface/driver split — mirrors `scope` / `scope-pgsql` and `config` / `config-pgsql` patterns exactly. Total Tier 2 package count grows from FEATURES.md's stated 14 to 15; FEATURES.md is updated in task 015.
- **`#[Scoped]` attribute kept** as an ergonomic shortcut for merchant-defined config classes. Removed from `ConfigRegistryBuilder` in `config`; re-introduced via a boot-time attribute scan in `config-scope`'s `module.php` that walks `ConfigClassDiscovery`'s output and feeds `ScopedFieldRegistry`. Merchants get the same one-line declaration today, just opt-in via installing `config-scope`.
- **CLI `--scope` parsing comes back via Preferences in config-scope.** New `ScopedSetCommand`, `ScopedUnsetCommand`, `ScopedConfigGetCommand` extend the descoped Tier 1 commands, override `execute()` to parse `--scope=…`, and bind via `#[Preference(replaces: SetCommand::class)]` etc. Single command name surface; Tier 1 keeps clean help text. Matches the catalog-scope `ScopedProductGridComponent` shape.
- **`config-locale` and `config-market` ship as no-op placeholder bridges today**, mirroring P4's `catalog-market`. Their `module.php` carries an empty `boot` closure with a documenting docblock. Merchants who install them get axis registration coverage for whatever fields they later mark `#[Scoped(axes: ['locale'])]` / `#[Scoped(axes: ['market'])]` on their own config classes — but the framework itself ships no markommerce-owned translatable/market-varying config keys yet.
- **No companion-entity decorator pattern for config.** Catalog's companion approach exploits Marko's `#[Table(extends: …)]` entity-extension mechanism. Config rows are not entities — they are keyed strings persisted by a hand-rolled emitter. The override storage is therefore a peer table managed by `config-scope-pgsql`'s own emitter, not a column-add on `config_values`.
- **Override storage API surface:** new `ScopedConfigStorageInterface` in `config-scope` declares `loadOverrides(string $key): array<string, mixed>` (signature => value), `loadManyOverrides(list<string> $keys): array<string, array<string, mixed>>`, `saveOverride(string $key, string $signature, mixed $value): void`, `deleteOverride(string $key, string $signature): void`. Disjoint from `ConfigStorageInterface` (global-only) so Tier 1 implementations need not know about scopes. `InMemoryScopedConfigStorage` (test impl) ships in `config-scope`; `PgsqlScopedConfigStorage` in `config-scope-pgsql`.
- **`OverrideMatcher` and `AxisNotDeclaredException` relocate** from `config` to `config-scope`. No back-compat shim — pre-1.0, rip-and-replace per FEATURES.md.
- **`ConfigDefinition` no longer carries `axes`.** In `config-scope`, the resolver consults `ScopedFieldRegistry::axesForProperty($definition->configClass, $definition->field)` per-resolve. One source of truth for axes (the registry); definition stays a plain value object.
- **`ScopedConfigResolver` extends `ConfigResolver`** via `#[Preference(replaces: ConfigResolver::class)]` and overrides `resolved()`; it injects extra deps (`ScopedConfigStorageInterface`, `OverrideMatcher`, `ScopeContext`, `ScopedFieldRegistry`) on top of the parent's constructor signature. **Visibility prereq for the override to compile:** `ConfigResolver` today declares its constructor-promoted properties (`$configRegistry`, `$valueCaster`, `$secretCipher`, etc.) as `private`. To let the Scoped subclass do `$this->configRegistry->definition(...)`, `$this->secretCipher->decrypt(...)`, `$this->valueCaster->cast(...)` from inside `resolvedAt()`, task 002 MUST relax the parent's property visibility from `private` to `protected`. Apply the same change to `ConfigWriter` (`$registry`, `$storage`, `$cipher`) and `CachingConfigResolver` (`$configResolver`, `$configCache`, `$configRegistry`). `CachingConfigResolver::buildCacheKey()` must also be relaxed from `private` to `protected` so the Scoped cache can override it. Without this relaxation, the child classes will raise fatal errors at construction or runtime — the `ScopedProductGridComponent` precedent did not exercise this because its `data()` method operates only on the parent's public return value.
- **`ScopedCachingConfigResolver` mirrors that pattern** for the cache layer — Preferences-replaces `CachingConfigResolver`, builds cache keys with the active `ScopeContext` × `ScopedFieldRegistry` axes. **Container wiring detail:** Tier 1's `config/module.php` binds `ConfigResolver::class => closure-returning-CachingConfigResolver(ConfigResolver(...))`. When the Preference swaps `ConfigResolver` to `ScopedConfigResolver`, the Container's resolver looks up `bindings[ScopedConfigResolver]` (not set) and auto-wires it — **bypassing the Tier 1 factory and losing the caching wrap**. `config-scope/module.php` MUST therefore override the binding with its own factory: `ConfigResolver::class => closure-returning-ScopedCachingConfigResolver(ScopedConfigResolver(...))`. The `#[Preference]` attribute on `ScopedConfigResolver` is still useful for callers that type-hint `ConfigResolver` in constructors (auto-wired path) but the explicit factory binding is what preserves caching in container-resolved consumers. The Tier 2 E2E test asserts `$container->get(ConfigResolver::class) instanceof ScopedCachingConfigResolver` AND that the underlying inner resolver is `ScopedConfigResolver`.
- **`ScopedConfigWriter`/`ScopedConfigWriterInterface` extends the base writer** and re-introduces `setOverride`/`unsetOverride` taking `ScopeSignature`. Preferences-replaces `ConfigWriter` so any consumer typed against `ConfigWriterInterface` still gets a `Markommerce\Config\Contracts\ConfigWriterInterface` reference; a consumer that wants override methods type-hints `ScopedConfigWriterInterface` instead.

### Files / mechanisms touched

**Config production code (descope):**
- `packages/config/composer.json` — drop `markommerce/scope` from `require`.
- `packages/config/module.php` — drop `ScopeContext` / `ScopeRegistryInterface` imports; descope `ConfigResolver` factory; descope `ConfigRegistry` build step (no longer passes scope registry).
- `packages/config/src/ConfigResolver.php` — drop `ScopeContext`, `OverrideMatcher` constructor deps; drop `resolvedAt()` method; `resolved()` returns `row->value` or `definition->defaultValue`.
- `packages/config/src/ConfigWriter.php` — drop `setOverride()`, `unsetOverride()`, the `ScopeSignature` import, axis validation; keep `setGlobal`/`unsetGlobal` + retry logic.
- `packages/config/src/Contracts/ConfigWriterInterface.php` — drop `setOverride`/`unsetOverride` methods + `ScopeSignature` import.
- `packages/config/src/Cache/CachingConfigResolver.php` — drop `ScopeContext` constructor dep, drop `buildCacheKey()` axes branch; cache key becomes `definition->key`.
- `packages/config/src/Registry/ConfigRegistryBuilder.php` — drop `ScopeRegistryInterface` parameter on `build()`, drop `resolveAxes()`, drop `#[Scoped]` import. Builder produces `ConfigDefinition` with no axes field.
- `packages/config/src/Command/SetCommand.php` — drop `--scope` option, `parseScope()`, `ScopeSignature` import; only calls `writer->setGlobal(...)`.
- `packages/config/src/Command/UnsetCommand.php` — same.
- `packages/config/src/Command/ConfigGetCommand.php` — drop `ScopeRegistryInterface` ctor dep, drop `buildContext()`, drop the ad-hoc `OverrideMatcher`/`SignatureCandidateEnumerator`/`ScopeContext` wiring; just call `resolver->resolved($definition->configClass, $definition->field)`.
- `packages/config/src/Storage/InMemoryConfigStorage.php` — drop `overrides` handling; "empty row" means `value === null`.
- `packages/config/src/ValueObjects/ConfigDefinition.php` — drop `axes` property + corresponding constructor parameter.
- `packages/config/src/ValueObjects/ConfigRow.php` — drop `overrides` property, `withOverride()`, `withoutOverride()`. Constructor takes `key, value, version, updatedAt` only.
- `packages/config/src/Resolution/OverrideMatcher.php` — **DELETED** (relocates to config-scope).
- `packages/config/src/Exceptions/AxisNotDeclaredException.php` — **DELETED** (relocates to config-scope).
- `packages/config/src/Contracts/ConfigStorageInterface.php` — unchanged signature (ConfigRow shape change is transparent at this layer).

**Config tests:**
- `packages/config/tests/Fakes/FakeScopeRegistry.php` — **DELETED**.
- `packages/config/tests/Unit/Resolution/OverrideMatcherTest.php` — **DELETED** (relocates).
- `packages/config/tests/Unit/ValueObjects/ConfigRowTest.php` — drop `overrides`/`withOverride`/`withoutOverride` cases.
- `packages/config/tests/Unit/ValueObjects/ConfigDefinitionTest.php` — drop `axes` cases.
- `packages/config/tests/Unit/ConfigResolverTest.php` — keep only the global-resolution cases. Relocate scoped cases to `config-scope/tests/Unit/ScopedConfigResolverTest.php`.
- `packages/config/tests/Unit/ConfigWriterTest.php` — keep only global cases. Relocate `setOverride`/`unsetOverride` cases to `config-scope/tests/Unit/ScopedConfigWriterTest.php`.
- `packages/config/tests/Unit/Cache/CachingConfigResolverTest.php` — keep only the no-axes cases.
- `packages/config/tests/Unit/Registry/ConfigRegistryBuilderTest.php` — drop the `ScopedConfig`/`ScopedWithUnknownAxisConfig`/`captures axes from #[Scoped]` cases (relocate to config-scope's boot scan test).
- `packages/config/tests/Unit/Resolver/ConfigResolverGetTest.php` — drop scoped resolution cases.
- `packages/config/tests/Unit/Command/SetCommandTest.php`, `UnsetCommandTest.php`, `ConfigGetCommandTest.php`, `ConfigListCommandTest.php` — drop scope-aware cases.
- `packages/config/tests/Unit/SecretCipherIntegrationTest.php` — drop scope imports; if a test case asserts scoped secret round-trips, relocate.
- `packages/config/tests/Feature/ModulePhpTest.php` — drop `ScopeRegistryInterface` binding from `bootModuleContainer()`; remove scope-related boot assertions.
- `packages/config/tests/Unit/Storage/InMemoryConfigStorageTest.php` — drop overrides cases.
- `packages/config/tests/PackageScaffoldingTest.php` — drop the `requires markommerce/scope` assertion; add `does NOT require markommerce/scope` instead.
- **NEW**: `packages/config/tests/Unit/ScopeDecouplingTest.php` — safety net asserting `packages/config/src` contains no `Markommerce\Scope\…` imports, no `OverrideMatcher`, no `AxisNotDeclaredException`, no `ScopeSignature`, no `ScopeContext` references.
- **NEW**: `packages/config/tests/Unit/ComposerManifestTest.php` extension — assert that `markommerce/scope` is NOT in `require`, and that `markommerce/config-scope`, `config-scope-pgsql`, `config-locale`, `config-market` are NOT in `require` either.

**Config-pgsql descope:**
- `packages/config-pgsql/src/Schema/ConfigValuesTableEmitter.php` — drop the `overrides JSONB NOT NULL DEFAULT '{}'::jsonb` column from `CREATE TABLE`; drop the `CREATE INDEX … overrides_gin USING GIN (overrides)` statement.
- `packages/config-pgsql/src/PgsqlConfigStorage.php` — drop `overrides` from SELECT/INSERT/UPDATE statements; drop `overridesJson` serialization; hydrate row without overrides.
- `packages/config-pgsql/tests/Feature/ConfigValuesTableEmitterTest.php` — drop assertions about the `overrides` column / GIN index.
- `packages/config-pgsql/tests/Feature/PgsqlConfigStorageTest.php` — drop override read/write cases (relocate to config-scope-pgsql's integration test).
- `packages/config-pgsql/README.md` — drop mentions of per-scope overrides.

**Root composer.json + monorepo registration:**
- `composer.json` (root) — add `markommerce/config-scope`, `markommerce/config-scope-pgsql`, `markommerce/config-locale`, `markommerce/config-market` to `require`. Add `Markommerce\\ConfigScope\\Tests\\`, `Markommerce\\ConfigLocale\\Tests\\`, `Markommerce\\ConfigMarket\\Tests\\` to `autoload-dev.psr-4` (paths: `packages/config-scope/tests/`, etc.). Per repo convention (mirroring `scope-pgsql` and `config-pgsql`), the PgSql driver's tests namespace `Markommerce\\ConfigScope\\PgSql\\Tests\\` is registered ONLY in the package-local `composer.json`'s `autoload-dev`, not at the root. `repositories.packages/*` already globs new packages.

**New: `packages/config-scope/`:**
- `composer.json` — `name: markommerce/config-scope`, requires `markommerce/config`, `markommerce/scope`. `autoload psr-4: Markommerce\\ConfigScope\\: src/`.
- `module.php` — `require: { markommerce/config: '*', markommerce/scope: '*' }`; `bindings` (writer interface), Preferences via discovery (not module.php), `boot` closure that injects `ConfigClassDiscovery` + `ScopedFieldRegistry` and scans `#[Scoped]` on discovered config classes.
- `src/Contracts/ScopedConfigStorageInterface.php` — `loadOverrides(string $key): array<string, mixed>`, `loadManyOverrides(list<string> $keys): array<string, array<string, mixed>>`, `saveOverride(string $key, string $signature, mixed $value): void`, `deleteOverride(string $key, string $signature): void`.
- `src/Contracts/ScopedConfigWriterInterface.php` — `extends Markommerce\Config\Contracts\ConfigWriterInterface`; declares `setOverride(string $key, ScopeSignature $signature, mixed $value): void`, `unsetOverride(string $key, ScopeSignature $signature): void`.
- `src/Storage/InMemoryScopedConfigStorage.php` — implements the storage interface; backed by `array<string, array<string, mixed>>`.
- `src/Resolution/OverrideMatcher.php` — relocated from config (namespace `Markommerce\ConfigScope\Resolution`).
- `src/Exceptions/AxisNotDeclaredException.php` — relocated (namespace `Markommerce\ConfigScope\Exceptions`). Only the `forPropertyAndAxis(...)` factory is retained (used by `ScopedConfigWriter` for runtime axis-on-signature validation). The `forAxisOnProperty(...)` factory used by `ConfigRegistryBuilder` for build-time validation is dropped — `ScopedFieldRegistry::register()` already throws `Markommerce\Scope\Exceptions\UnknownAxisException` for unknown axes, which the boot-time `#[Scoped]` scan in config-scope's module.php propagates as-is. Task 008's requirements update accordingly.
- `src/ScopedConfigResolver.php` — `extends Markommerce\Config\ConfigResolver`; carries `#[Preference(replaces: ConfigResolver::class)]`. Constructor adds `ScopedConfigStorageInterface`, `OverrideMatcher`, `ScopeContext`, `ScopedFieldRegistry` on top of parent ctor. Overrides `resolved()` and adds `resolvedAt(class, field, ScopeContext)`.
- `src/Cache/ScopedCachingConfigResolver.php` — `extends Markommerce\Config\Cache\CachingConfigResolver`; carries `#[Preference(replaces: CachingConfigResolver::class)]`. Builds cache key using `ScopeContext` × `ScopedFieldRegistry::axesForProperty(class, field)`.
- `src/ScopedConfigWriter.php` — `extends Markommerce\Config\ConfigWriter implements ScopedConfigWriterInterface`. Carries `#[Preference(replaces: ConfigWriter::class)]`. Implements `setOverride`/`unsetOverride` against `ScopedConfigStorageInterface`.
- `src/Command/ScopedSetCommand.php` — `extends Markommerce\Config\Command\SetCommand`; `#[Preference(replaces: SetCommand::class)]`. Re-introduces `--scope=…` parsing → `ScopeSignature` → `writer->setOverride(...)`.
- `src/Command/ScopedUnsetCommand.php` — same shape.
- `src/Command/ScopedConfigGetCommand.php` — same shape; builds synthetic `ScopeContext`, calls `resolver->resolvedAt(...)`.
- `tests/Pest.php`, `tests/PackageScaffoldingTest.php`, `tests/ReadmeTest.php` — standard scaffolding.
- `tests/Unit/AutoloadableClassesTest.php` — assert all new classes load.
- `tests/Unit/Resolution/OverrideMatcherTest.php` — relocated from config.
- `tests/Unit/Storage/InMemoryScopedConfigStorageTest.php` — new.
- `tests/Unit/ScopedConfigResolverTest.php` — covers the relocated scoped cases and the new axes-from-registry path.
- `tests/Unit/ScopedConfigWriterTest.php` — covers the relocated override cases.
- `tests/Unit/Cache/ScopedCachingConfigResolverTest.php` — covers per-context cache keying.
- `tests/Unit/Command/ScopedSetCommandTest.php`, `ScopedUnsetCommandTest.php`, `ScopedConfigGetCommandTest.php` — relocated and adapted.
- `tests/Feature/BootContributionTest.php` — boot the full module stack (scope + config + config-scope) and assert: (a) `#[Scoped]` on a fixture config class populates `ScopedFieldRegistry`, (b) `ConfigResolver` resolved via container is a `ScopedConfigResolver` instance, (c) the writer is a `ScopedConfigWriter`, (d) module boot ordering is correct (config-scope after both config and scope).
- `README.md`, `LICENSE`, `.gitattributes` — per package standards.

**New: `packages/config-scope-pgsql/`:**
- `composer.json` — `name: markommerce/config-scope-pgsql`, requires `markommerce/config-scope`, `marko/database-pgsql`. `autoload psr-4: Markommerce\\ConfigScope\\PgSql\\: src/`. `autoload-dev psr-4: Markommerce\\ConfigScope\\PgSql\\Tests\\: tests/`.
- `module.php` — `bindings: { ScopedConfigStorageInterface::class => factory(PgsqlScopedConfigStorage::class) }`.
- `src/PgsqlScopedConfigStorage.php` — implements `ScopedConfigStorageInterface` against a `config_value_overrides` table.
- `src/Schema/ConfigValueOverridesTableEmitter.php` — emits `CREATE TABLE IF NOT EXISTS config_value_overrides (config_key VARCHAR(255), signature VARCHAR(255), value JSONB NOT NULL, version INTEGER NOT NULL DEFAULT 0, updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), PRIMARY KEY (config_key, signature))`. No GIN index needed — primary key suffices for lookup.
- `tests/Pest.php`, `PackageScaffoldingTest.php`, `ReadmeTest.php`.
- `tests/Feature/ConfigValueOverridesTableEmitterTest.php`.
- `tests/Feature/PgsqlScopedConfigStorageTest.php`.
- `tests/Feature/Helpers/PostgresTestConnection.php` — copy/share Postgres connection helper.
- `README.md`, `LICENSE`, `.gitattributes`.

**New: `packages/config-locale/`** (no-op bridge placeholder):
- `composer.json` — requires `markommerce/config-scope`, `markommerce/locale`. `autoload psr-4: Markommerce\\ConfigLocale\\: src/`.
- `module.php` — `require: { markommerce/config-scope: '*', markommerce/locale: '*' }`. Empty `boot` closure with documenting docblock referencing FEATURES.md placeholder status.
- `src/.gitkeep`.
- `tests/Pest.php`, `PackageScaffoldingTest.php`, `ReadmeTest.php`.
- `tests/Unit/BootClosureTest.php` — assert boot is callable, runs no-op, leaves the registry empty for arbitrary entity classes.
- `tests/Unit/ComposerDepsTest.php` — assert the two requires.
- `README.md` documenting placeholder status.

**New: `packages/config-market/`** (no-op bridge placeholder):
- Mirror of `config-locale` with `markommerce/market` instead of `markommerce/locale`.

**FEATURES.md update:**
- P5 row status `pending` → `completed`; plan field `tbd` → `config-scope-decouple`.
- Tier 2 desired package list: add `config-scope-pgsql` after `config-scope`.
- Tier 2 headless count: 14 → 15. Tier 2 storefront: 15 → 16. Tier 3: 17 → 18.
- Tier 1 row: clarify that `config-pgsql` no longer emits an `overrides` JSONB column.
- Proposed-new-packages table: change `🆕 markommerce/config-scope` from "🆕" to no marker; add `markommerce/config-scope-pgsql` with the same change. Add `🆕 markommerce/config-locale` and `🆕 markommerce/config-market` (still "🆕" until merged but flip after).

**Docs site:**
- New: `docs/src/content/docs/packages/config-scope.md`.
- New: `docs/src/content/docs/packages/config-scope-pgsql.md`.
- New: `docs/src/content/docs/packages/config-locale.md`.
- New: `docs/src/content/docs/packages/config-market.md`.
- Updated: `docs/src/content/docs/packages/config.md` — strip per-scope override example, point to config-scope.
- Updated: `docs/src/content/docs/packages/config-pgsql.md` — drop overrides-column reference.
- New: `tests/Unit/Docs/ConfigScopeDecouplePagesTest.php` — assert all five pages exist and contain expected anchor sections (mirror P4's `CatalogMarketExtractPagesTest`).

## Scope

### In Scope
- Strip every `Markommerce\Scope\…` import, `ScopeContext`/`ScopeSignature`/`OverrideMatcher` reference, and `#[Scoped]` consumption from `markommerce/config` production code, value objects, exceptions, CLI commands, module.php, and tests.
- Drop the `overrides` JSONB column and its GIN index from `config-pgsql`'s `config_values` schema emitter and storage class.
- Create four new packages — `markommerce/config-scope`, `markommerce/config-scope-pgsql`, `markommerce/config-locale`, `markommerce/config-market` — with full composer.json, module.php (where applicable), READMEs, LICENSE, .gitattributes, Pest configuration, scaffolding tests, and integration tests per project conventions.
- Move `OverrideMatcher` and `AxisNotDeclaredException` from `config` into `config-scope`; their tests move with them.
- Re-introduce per-scope override resolution and per-scope writing via Preference-replacement classes in `config-scope` (`ScopedConfigResolver`, `ScopedCachingConfigResolver`, `ScopedConfigWriter` + `ScopedConfigWriterInterface`, `ScopedSetCommand`, `ScopedUnsetCommand`, `ScopedConfigGetCommand`).
- Persist overrides via a new `config_value_overrides` table emitted by `config-scope-pgsql`'s `ConfigValueOverridesTableEmitter` and a `PgsqlScopedConfigStorage` implementing the new `ScopedConfigStorageInterface`.
- Boot-time `#[Scoped]` attribute scan in `config-scope/module.php`: discover config classes via `ConfigClassDiscovery`, reflect properties, register `(configClass, property, axes)` triples with `ScopedFieldRegistry`. Preserves today's ergonomic shortcut.
- Add `ScopeDecouplingTest` and `ComposerManifestTest` assertions to `packages/config` (production-code grep + composer require check).
- Tier 2/3 end-to-end integration test in `config-scope` that boots scope + scope-pgsql + locale + market + config + config-pgsql + config-scope + config-scope-pgsql + config-locale + config-market, persists a scoped override via `ScopedConfigWriter`, and asserts `ScopedConfigResolver` returns it under the matching context (and falls back through the hierarchy).
- READMEs for all four new packages; update `config` and `config-pgsql` READMEs to remove scope mentions.
- Docs site pages for the four new packages plus updates to `config.md` and `config-pgsql.md`.
- Root `composer.json` updates (require + autoload-dev psr-4).
- `FEATURES.md` status flip + tier count refresh.

### Out of Scope
- Renaming `theme-blank` (still an open question in FEATURES.md).
- Adding tier meta-packages (`starter-shop` etc.) — open question deferred.
- Introducing merchant-overridable bridge mappings — deferred per FEATURES.md.
- Channel axis — out of scope per FEATURES.md.
- Shipping concrete markommerce-owned translatable or market-varying config classes (no current consumer in the framework). `config-locale` and `config-market` are explicitly placeholder bridges.
- Refactoring `ConfigClassDiscovery`, `ProxyGenerator`, `ProxyLocator`, or the proxy-autoload pipeline — they remain in config and are unaffected by the descope.
- Removing the `#[Scoped]` attribute from `markommerce/scope` — still useful for merchant entities (see P2 decision).
- Demo packages — `frontend-demo` does not consume config's scope-aware API today; `grep` confirms only `catalog-storefront/tests/Feature/Tier1EndToEndTest.php` references config modules, and that reference is name-string-only (no API call to verify).
- A `config-channel` bridge — out of scope until the channel axis itself materializes.
- Schema migrations / data-migration tooling — markommerce is pre-1.0; merchants run the emitter directly.

## Success Criteria
- [ ] `grep -rn "Markommerce\\\\Scope" packages/config/src` returns zero matches.
- [ ] `grep -rn "Markommerce\\\\Scope" packages/config-pgsql/src` returns zero matches.
- [ ] `packages/config/composer.json` does not require `markommerce/scope`.
- [ ] `packages/config/src/Resolution/OverrideMatcher.php` does not exist; `packages/config-scope/src/Resolution/OverrideMatcher.php` does.
- [ ] `packages/config/src/Exceptions/AxisNotDeclaredException.php` does not exist; `packages/config-scope/src/Exceptions/AxisNotDeclaredException.php` does.
- [ ] `ConfigDefinition` has no `axes` property; `ConfigRow` has no `overrides` property; `ConfigWriterInterface` declares no `setOverride`/`unsetOverride`.
- [ ] `config-pgsql`'s `ConfigValuesTableEmitter::createStatements()` returns no statement containing `overrides` or `_overrides_gin`.
- [ ] `markommerce/config` test suite passes without any of `markommerce/scope`, `markommerce/config-scope`, or `markommerce/config-scope-pgsql` being booted.
- [ ] `markommerce/config-scope` exists, ships the new storage interface + InMemory impl, the relocated OverrideMatcher and AxisNotDeclaredException, ScopedConfigResolver / ScopedCachingConfigResolver / ScopedConfigWriter Preference-replacements, and three scoped CLI commands. All scaffolding + unit + boot-contribution tests green.
- [ ] `markommerce/config-scope-pgsql` exists, ships `PgsqlScopedConfigStorage` + `ConfigValueOverridesTableEmitter`, binds `ScopedConfigStorageInterface` → `PgsqlScopedConfigStorage`. Postgres integration tests green.
- [ ] `markommerce/config-locale` and `markommerce/config-market` exist as no-op bridges with empty boot closures, passing scaffolding + readme + composer-deps + boot-closure tests.
- [ ] Tier 2/3 E2E test boots the full module stack against Postgres, sets a scoped override via `ScopedConfigWriter::setOverride(...)`, and asserts `ScopedConfigResolver::resolved(...)` returns it under the matching `ScopeContext`, falls back to the global value when context doesn't match, and falls back to the property default when no row exists.
- [ ] `ScopeDecouplingTest` passes in `packages/config/tests/Unit/`.
- [ ] `ComposerManifestTest` in `packages/config/tests/Unit/` asserts `markommerce/scope`, `markommerce/config-scope`, `markommerce/config-scope-pgsql`, `markommerce/config-locale`, `markommerce/config-market` are NOT in config's require block.
- [ ] Root `composer.json` requires the four new packages and includes their psr-4 autoload-dev entries.
- [ ] `composer test:all` from the monorepo root passes.
- [ ] PHPStan level 8 + PHP-CS-Fixer clean for all touched packages.
- [ ] Docs site builds with the four new package pages and the updated config / config-pgsql pages.
- [ ] FEATURES.md P5 row reads `completed`, plan field reads `config-scope-decouple`; Tier 2/3 package counts refreshed.
- [ ] All requirements from each task file have passing tests.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Strip scope from config value objects, storage, and OverrideMatcher/AxisNotDeclaredException deletion | — | completed |
| 002 | Strip scope from ConfigResolver, CachingConfigResolver, and ConfigRegistryBuilder | 001 | completed |
| 003 | Strip scope from ConfigWriter, ConfigWriterInterface, and CLI commands | 001 | completed |
| 004 | Strip scope from config tests; delete FakeScopeRegistry; add ScopeDecouplingTest + ComposerManifestTest | 002, 003 | completed |
| 005 | Strip scope from config-pgsql (PgsqlConfigStorage, ConfigValuesTableEmitter, tests, README) | 001 | completed |
| 006 | Drop markommerce/scope from config/composer.json; register four new packages in root composer.json | 004, 005 | completed |
| 007 | Scaffold markommerce/config-scope (composer, module skeleton, README placeholder, scaffolding tests, AutoloadableClassesTest) | 006 | completed |
| 008 | Implement config-scope override storage layer: ScopedConfigStorageInterface, InMemoryScopedConfigStorage, relocated OverrideMatcher + AxisNotDeclaredException, boot-time `#[Scoped]` attribute scan | 007 | completed |
| 009 | Implement ScopedConfigResolver and ScopedCachingConfigResolver via `#[Preference]` | 008 | completed |
| 010 | Implement ScopedConfigWriter + ScopedConfigWriterInterface, ScopedSetCommand, ScopedUnsetCommand, ScopedConfigGetCommand via `#[Preference]` | 008 | completed |
| 011 | Scaffold + implement markommerce/config-scope-pgsql (composer, module, ConfigValueOverridesTableEmitter, PgsqlScopedConfigStorage, tests, README) | 008 | completed |
| 012 | Scaffold markommerce/config-locale and markommerce/config-market (no-op bridge placeholders, tests, READMEs) | 007 | completed |
| 013 | Tier 2/3 end-to-end integration test (Postgres-backed full container boot, override round-trip + fallback hierarchy) | 009, 010, 011, 012 | completed |
| 014 | READMEs for four new packages; update config + config-pgsql READMEs; docs site pages (five touch points) + ConfigScopeDecouplePagesTest | 007, 011, 012 | completed |
| 015 | Update FEATURES.md (P5 status → completed; tier counts refreshed) | 013, 014 | completed |

## Architecture Notes

### Module layout after P5
```
packages/
  config/                                # Tier 1 — plain k/v store
    src/
      Attributes/Config.php              # unchanged (key + secret only)
      Cache/{CachingConfigResolver,RequestConfigCache}.php  # descoped
      Casting/ValueCaster.php
      Command/{ConfigGet,ConfigList,Generate,Set,Unset}Command.php  # descoped
      Contracts/{ConfigCache,ConfigStorage,ConfigWriter,SecretCipher}Interface.php  # writer descoped
      Discovery/ConfigClassDiscovery.php
      Encryption/{Null,Sodium}SecretCipher.php
      Exceptions/{ConfigKeyConflict,ConfigNotFound,InvalidConfigClass,InvalidConfigValue,ProxyNotGenerated,SecretCipher,StaleConfigWrite}Exception.php
      Middleware/ConfigCacheResetMiddleware.php
      Proxy/{ProxyAutoloader,ProxyGenerator,ProxyLocator,ProxyWriter,PreferenceAwareScanner}.php
      Registry/{ConfigRegistry,ConfigRegistryBuilder}.php  # builder descoped
      Storage/InMemoryConfigStorage.php  # descoped
      ValueObjects/{ConfigDefinition,ConfigRow}.php  # descoped
      ConfigResolver.php                 # descoped
      ConfigWriter.php                   # descoped
    module.php                           # descoped
    composer.json                        # no markommerce/scope
  config-pgsql/                          # Tier 1 driver — no overrides column
    src/{PgsqlConfigStorage.php, Schema/ConfigValuesTableEmitter.php}  # descoped
    composer.json
  config-scope/                          # NEW — Tier 2 override machinery
    src/
      Cache/ScopedCachingConfigResolver.php
      Command/{ScopedSet,ScopedUnset,ScopedConfigGet}Command.php
      Contracts/{ScopedConfigStorage,ScopedConfigWriter}Interface.php
      Exceptions/AxisNotDeclaredException.php
      Resolution/OverrideMatcher.php
      Storage/InMemoryScopedConfigStorage.php
      ScopedConfigResolver.php
      ScopedConfigWriter.php
    module.php                           # boot scans #[Scoped] on discovered config classes
    composer.json                        # requires markommerce/config + markommerce/scope
    tests/...
  config-scope-pgsql/                    # NEW — Tier 2 driver for override storage
    src/{PgsqlScopedConfigStorage.php, Schema/ConfigValueOverridesTableEmitter.php}
    module.php                           # binds ScopedConfigStorageInterface
    composer.json                        # requires markommerce/config-scope + marko/database-pgsql
  config-locale/                         # NEW — no-op bridge placeholder (mirror of catalog-market)
    src/.gitkeep
    module.php                           # require: config-scope + locale; empty boot
    composer.json
  config-market/                         # NEW — no-op bridge placeholder
    src/.gitkeep
    module.php                           # require: config-scope + market; empty boot
    composer.json
```

### Storage interfaces after P5
```php
// Markommerce\Config\Contracts\ConfigStorageInterface (unchanged surface, ConfigRow shape changed)
interface ConfigStorageInterface
{
    public function load(string $key): ?ConfigRow;             // ConfigRow has no overrides
    public function loadMany(array $keys): array;
    public function compareAndSave(string $key, ConfigRow $row, int $expectedVersion): bool;
}

// Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface (new, disjoint)
interface ScopedConfigStorageInterface
{
    /** @return array<string, mixed>  signature => raw value */
    public function loadOverrides(string $key): array;

    /**
     * @param list<string> $keys
     * @return array<string, array<string, mixed>>  configKey => (signature => value)
     */
    public function loadManyOverrides(array $keys): array;

    public function saveOverride(string $key, string $signature, mixed $value): void;

    public function deleteOverride(string $key, string $signature): void;
}
```
Two storage backends are queried side-by-side: `ScopedConfigResolver` calls `ConfigStorage::load($key)` for the global value and `ScopedConfigStorage::loadOverrides($key)` for the overrides. No transactional coupling — overrides and globals can be written independently.

### ScopedConfigResolver shape
```php
namespace Markommerce\ConfigScope;

use Marko\Core\Container\Preference;
use Markommerce\Config\ConfigResolver;
// ...

#[Preference(replaces: ConfigResolver::class)]
class ScopedConfigResolver extends ConfigResolver
{
    public function __construct(
        ConfigRegistry $configRegistry,
        ConfigStorageInterface $configStorage,
        ValueCaster $valueCaster,
        SecretCipherInterface $secretCipher,
        ProxyLocator $proxyLocator,
        PreferenceRegistry $preferenceRegistry,
        private ScopedConfigStorageInterface $scopedConfigStorage,
        private OverrideMatcher $overrideMatcher,
        private ScopeContext $scopeContext,
        private ScopedFieldRegistry $scopedFieldRegistry,
    ) {
        parent::__construct(
            $configRegistry, $configStorage, $valueCaster,
            $secretCipher, $proxyLocator, $preferenceRegistry,
        );
    }

    public function resolved(string $configClass, string $field): mixed
    {
        return $this->resolvedAt($configClass, $field, $this->scopeContext);
    }

    public function resolvedAt(string $configClass, string $field, ScopeContext $context): mixed
    {
        $definition = $this->configRegistry->definition($configClass, $field);
        $axes = $this->scopedFieldRegistry->axesForProperty($definition->configClass, $definition->field);

        // Unscoped field: just delegate to parent (global-or-default)
        if ($axes === []) {
            return parent::resolved($configClass, $field);
        }

        $overrides = $this->scopedConfigStorage->loadOverrides($definition->key);
        $overrideValue = $this->overrideMatcher->match($overrides, $axes, $context);

        if ($overrideValue !== null) {
            if ($definition->secret) {
                $decrypted = $this->secretCipher->decrypt($overrideValue);
                $overrideValue = json_decode($decrypted, true);
            }
            return $this->valueCaster->cast($overrideValue, $definition);
        }

        return parent::resolved($configClass, $field);  // falls back to global-or-default
    }
}
```
Note: `OverrideMatcher::match()` no longer takes a `ConfigRow` — it takes the `array<string, mixed>` overrides map directly, since overrides now live in a separate storage. Signature: `match(array $overrides, list<string> $axes, ScopeContext $context): mixed`.

### ScopedConfigWriter shape
```php
namespace Markommerce\ConfigScope;

#[Preference(replaces: ConfigWriter::class)]
class ScopedConfigWriter extends ConfigWriter implements ScopedConfigWriterInterface
{
    public function __construct(
        ConfigRegistry $registry,
        ConfigStorageInterface $storage,
        SecretCipherInterface $cipher,
        private ScopedConfigStorageInterface $scopedStorage,
        private ScopedFieldRegistry $scopedFieldRegistry,
    ) {
        parent::__construct($registry, $storage, $cipher);
    }

    public function setOverride(string $key, ScopeSignature $signature, mixed $value): void
    {
        $definition = $this->registry->byKey($key);
        $axes = $this->scopedFieldRegistry->axesForProperty($definition->configClass, $definition->field);

        foreach ($signature->axes() as $axis) {
            if (!in_array($axis, $axes, true)) {
                throw AxisNotDeclaredException::forPropertyAndAxis($key, $axis);
            }
        }

        if ($definition->secret && $value !== null) {
            $value = $this->cipher->encrypt(json_encode($value, JSON_THROW_ON_ERROR));
        }

        if ($value === null) {
            $this->scopedStorage->deleteOverride($key, $signature->toString());
            return;
        }

        $this->scopedStorage->saveOverride($key, $signature->toString(), $value);
    }

    public function unsetOverride(string $key, ScopeSignature $signature): void
    {
        $this->setOverride($key, $signature, null);
    }
}
```

### Boot-time `#[Scoped]` attribute scan (config-scope/module.php)
```php
return [
    'require' => [
        'markommerce/config' => '*',
        'markommerce/scope'  => '*',
    ],
    'boot' => function (
        ConfigClassDiscovery $discovery,
        ScopedFieldRegistry $registry,
    ): void {
        foreach ($discovery->discover() as $configClass) {
            $reflection = new ReflectionClass($configClass);
            foreach ($reflection->getProperties() as $property) {
                $attrs = $property->getAttributes(Scoped::class);
                if ($attrs === []) {
                    continue;
                }
                /** @var Scoped $scoped */
                $scoped = $attrs[0]->newInstance();
                $registry->register(
                    entityClass: $configClass,
                    property: $property->getName(),
                    axes: $scoped->axes,
                );
            }
        }
    },
];
```
`DependencyResolver` orders config-scope's boot after both `markommerce/config` (`ConfigClassDiscovery` must be wired) and `markommerce/scope` (`ScopedFieldRegistry` must be live). Bridge boot closures (`config-locale`, `config-market`) run after config-scope's boot because they declare `require: { markommerce/config-scope: '*' }`.

### Preference-based CLI command swap
Marko's `PreferenceRegistry` auto-discovers `#[Preference(replaces: …)]` at boot. **Important gotcha:** `CommandDiscovery` reflects classes carrying `#[Command]` directly and registers them with `CommandRegistry`, which throws `duplicateCommandName` when two classes share the same `#[Command(name: …)]`. The Scoped variants therefore MUST NOT carry their own `#[Command]` attribute. Instead, `ScopedSetCommand`, `ScopedUnsetCommand`, `ScopedConfigGetCommand` carry ONLY `#[Preference(replaces: \Markommerce\Config\Command\SetCommand::class)]` (etc.). `CommandDiscovery` finds the parent's `#[Command(name: 'config:set')]`; `CommandRegistry` stores the parent's `commandClass`. At runtime, `CommandRunner::run(...)` calls `$container->get($definition->commandClass)` which resolves the parent class — but the Container's preference lookup swaps it to the Scoped subclass before construction. Tier 1 ships `config:set` without `--scope`; Tier 2 ships the same name with `--scope` re-introduced via the Preference swap. No user-facing command rename, no duplicate command name registration.

### config_value_overrides table
```sql
CREATE TABLE IF NOT EXISTS "config_value_overrides" (
    config_key VARCHAR(255) NOT NULL,
    signature  VARCHAR(255) NOT NULL,
    value      JSONB        NOT NULL,
    version    INTEGER      NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    PRIMARY KEY (config_key, signature)
);
```
No GIN index — primary key suffices for the (config_key, signature) lookups the resolver issues. No FK to `config_values` — Tier 1 may not have created the table (`config-pgsql`'s emitter is opt-in), and an override without a global is a valid "default to override-or-fall-through-to-property-default" case. The pre-P5 `overrides` JSONB column on `config_values` is dropped in task 005.

### Tier 2/3 E2E test pattern
The integration test in `packages/config-scope/tests/Feature/Tier2EndToEndTest.php` mirrors `packages/catalog-scope/tests/Feature/Tier2EndToEndTest.php`:
- Build a `Container` manually; wire `PreferenceRegistry` first (so the Preference auto-discovery actually swaps classes).
- Boot the following module manifests via `DependencyResolver::resolve(...)`:
  - `marko/config`, `marko/core`, `marko/database`, `marko/database-pgsql`
  - `markommerce/scope`, `markommerce/scope-pgsql`
  - `markommerce/locale`, `markommerce/market`
  - `markommerce/config`, `markommerce/config-pgsql`
  - `markommerce/config-scope`, `markommerce/config-scope-pgsql`
  - `markommerce/config-locale`, `markommerce/config-market`
- Each manifest must carry `path: dirname(__DIR__, N)` so `PreferenceDiscovery::discoverInModule()` and `ConfigClassDiscovery::discover()` can scan the right `src/` directory.
- Create the `config_values` table via `ConfigValuesTableEmitter` (now descoped — no overrides column).
- Create the `config_value_overrides` table via `ConfigValueOverridesTableEmitter`.
- Define a fixture config class with one `#[Scoped(axes: ['locale'])]` property; require it inside the test so `ConfigClassDiscovery` finds it.
- Resolve `ConfigWriterInterface` from the container — assert it's a `ScopedConfigWriter`.
- Resolve `ConfigResolver` — assert it's a `ScopedCachingConfigResolver` wrapping a `ScopedConfigResolver`.
- Call `$writer->setGlobal('test/welcome.greeting', 'Hello')`.
- Call `$writer->setOverride('test/welcome.greeting', new ScopeSignature(['locale' => 'de']), 'Hallo')`.
- Set `ScopeContext->in('locale', 'de')`; assert `$resolver->resolved(...)` returns `'Hallo'`.
- Set `ScopeContext->in('locale', 'en')` (or clear); assert it returns `'Hello'`.
- Call `$writer->unsetOverride('test/welcome.greeting', new ScopeSignature(['locale' => 'de']))`; assert resolution drops back to the global.
- Add an analogous market-axis case to exercise `config-market`'s requires-without-fields path (assert the bridge boots cleanly with both locale and market axes present).

### Decoupling safety net
`packages/config/tests/Unit/ScopeDecouplingTest.php` walks every PHP file under `packages/config/src/` (skipping `vendor`) and asserts none of the following appear in any file's contents:
- `Markommerce\\Scope\\` (FQN prefix)
- `ScopeContext`, `ScopeSignature`, `ScopeRegistryInterface`, `ScopedFieldRegistry`, `SignatureCandidateEnumerator`, `OverrideMatcher`, `AxisNotDeclaredException`
- `#[Scoped(` (attribute usage)

Mirrors `ScopeDecouplingTest`, `StorefrontDecouplingTest`, `MarketDecouplingTest`. Any future PR that re-couples config to scope goes red.

## Risks & Mitigations

- **Risk:** `ConfigResolver`'s current `resolvedAt(...)` is called from `CachingConfigResolver::resolvedAt(...)` and from `ConfigGetCommand`. Dropping `resolvedAt` from the parent would break those callers if not relocated to `ScopedConfigResolver` (and the descoped `ConfigGetCommand` no longer calling it).
  - **Mitigation:** Task 002 removes `resolvedAt` from both `ConfigResolver` and `CachingConfigResolver` and updates `ConfigGetCommand` to call `resolved()` only. The Tier-2 `ScopedConfigResolver` and `ScopedCachingConfigResolver` introduce `resolvedAt` in their own subclass surface. Task 003's `ScopedConfigGetCommand` uses `resolvedAt` against the typed-as-`ScopedConfigResolver` instance from the container.

- **Risk:** `OverrideMatcher::match()`'s pre-P5 signature takes a `ConfigRow $row` and reads `$row->overrides`. After P5, overrides live in `ScopedConfigStorage`, not on the row. Forgetting to adapt the matcher signature would mean the relocated tests still pass while production code breaks at runtime.
  - **Mitigation:** Task 008's relocated `OverrideMatcher` has signature `match(array $overrides, list<string> $axes, ScopeContext $context): mixed`. The relocated `OverrideMatcherTest` is updated to pass an array, not a ConfigRow. PHPStan level 8 catches a residual `ConfigRow` import.

- **Risk:** The `overrides` column drop from `config_values` is a destructive schema change. Any pre-existing Postgres database that has the column will keep it; the emitter is `CREATE TABLE IF NOT EXISTS …` so it never modifies the existing schema. A merchant who already ran the Tier 1 emitter and now installs Tier 2 must run `config-scope-pgsql`'s emitter — and the Tier 1 table will still have its unused overrides column. Acceptable because markommerce is pre-1.0, but document.
  - **Mitigation:** Task 005 updates the `config-pgsql` README with a note: "P5 dropped the `overrides` column. Existing merchants upgrading must run `ALTER TABLE config_values DROP COLUMN overrides` manually if they want to reclaim disk." Task 014's `config-scope-pgsql` README documents the new table.

- **Risk:** Marko's `PreferenceDiscovery` requires `ModuleManifest::path` to be set when scanning module-bundled classes for `#[Preference]` attributes. The catalog-market Tier 3 plan called this out as the most common test bug. Any test (or app boot) that constructs a manifest without `path` for `config-scope` will silently skip the Preference scan, leaving `ConfigResolver` un-swapped and the test green-but-wrong.
  - **Mitigation:** Task 007's `BootContributionTest` and task 013's `Tier2EndToEndTest` both set `path: dirname(__DIR__, N)` on every manifest. Task 013's spec spells out the exact construction. Additionally, the BootContributionTest asserts `$container->get(ConfigResolver::class) instanceof ScopedConfigResolver` — if the Preference doesn't fire, this assertion goes red.

- **Risk:** `ConfigGetCommand` in config builds an ad-hoc `ConfigResolver` (no container access). Today's code constructs it with `ScopeContext`, `OverrideMatcher`, etc.; after descope the constructor still works (fewer deps). But the Tier 2 `ScopedConfigGetCommand` cannot rebuild a `ScopedConfigResolver` ad-hoc — it needs `ScopedConfigStorageInterface` and `ScopeContext` injected. So `ScopedConfigGetCommand` resolves the `ConfigResolver` from the container instead of building one.
  - **Mitigation:** Task 010 specifies that `ScopedConfigGetCommand`'s constructor takes `ConfigResolver $resolver` (typed at the parent — container returns the swapped Scoped instance) plus a `ScopeRegistryInterface` for `--scope` parsing into a synthetic context. The container hands back the correctly-Preferenced resolver thanks to task 009.

- **Risk:** `ConfigRegistryBuilder` in config drops the `ScopeRegistryInterface` parameter from `build()`. Every caller (`config/module.php`, `ConfigRegistryBuilderTest.php`, `ConfigResolverTest.php`, `SecretCipherIntegrationTest.php`, the in-test fixture constructions) currently passes a fake — failing to update them all at once leaves a parse-time-pass / boot-fail.
  - **Mitigation:** Task 004 enumerates every test file that constructs a `ConfigRegistryBuilder` and updates the call sites in lock-step.

- **Risk:** `ConfigWriter`'s `writeWithRetry` and `compareAndSave` shape currently encodes "row is empty when value === null && overrides === []". After P5 the row has no overrides, so empty becomes "value === null". `InMemoryConfigStorage` and `PgsqlConfigStorage` both encode this — if one is updated and the other isn't, behavior diverges.
  - **Mitigation:** Tasks 001 (InMemory) and 005 (Pgsql) both list the "empty row" rule explicitly. The relocated `ConfigWriterTest` (in config) exercises the `value === null` empty-row case for the in-memory storage. `PgsqlConfigStorageTest` (in config-pgsql) exercises it for Postgres.

- **Risk:** The `BootContributionTest` for `config-scope` must verify the `#[Scoped]` attribute scan picks up a real config class. If the test inlines a fixture class via `eval` or temp file, `ConfigClassDiscovery`'s glob may not find it without a registered module path.
  - **Mitigation:** Task 008's BootContributionTest creates a temp module directory with a fixture `Markommerce\ConfigScope\Tests\Fixtures\TranslatableSiteConfig` class (`#[Config(key: 'test/site.greeting')] #[Scoped(axes: ['locale'])] public string $greeting = '…'`), wraps it in a `ModuleManifest` with `path` set, registers the manifest with the container's `ModuleRepository`, runs the boot closure, and asserts `ScopedFieldRegistry::axesForProperty(TranslatableSiteConfig::class, 'greeting')` returns `['locale']`.

- **Risk:** Three Preference-based replacements in config-scope (`ScopedConfigResolver`, `ScopedCachingConfigResolver`, `ScopedConfigWriter`) plus three more for commands. `PreferenceRegistry` raises `PreferenceConflictException` for same-priority conflicts. If a merchant later installs a third-party scope-aware config package with overlapping Preferences, boot fails.
  - **Mitigation:** Same-tier conflicts are intentional fail-loud behaviour. Document in `config-scope/README.md`. No code-level mitigation needed for P5 itself.

- **Risk:** `config-locale` and `config-market` ship empty `boot` closures. A merchant who installs them expecting per-locale settings to "just work" will see no behaviour change without also marking their config classes `#[Scoped(axes: ['locale'])]`.
  - **Mitigation:** Task 014's READMEs explicitly call out the placeholder status, point to FEATURES.md's tier rows, and document the one-line `#[Scoped]` declaration the merchant adds. The module.php boot closures carry leading docblocks explaining the same.

- **Risk:** Four new `autoload-dev.psr-4` paths in root composer.json. Forgetting one breaks the test loader for that package's tests when running from the monorepo root.
  - **Mitigation:** Task 006 lists all four entries in a single edit. Task 007 (config-scope scaffolding) re-verifies its own entry by running its scaffolding test from the root with `composer test`. Same for tasks 011 (config-scope-pgsql) and 012 (config-locale + config-market).

- **Risk:** `ConfigResolver` constructor signature changes (dropping `ScopeContext`, `OverrideMatcher`) means every test that constructs a resolver directly must be updated. The pre-P5 `makeConfigResolver()` helper in `ConfigResolverTest.php` builds ~11 args; the descoped version is ~7. PHP's named-argument calls are forgiving but missing positional args fail loudly.
  - **Mitigation:** Task 002 enumerates every direct `new ConfigResolver(...)` call site and updates them in lock-step. ConfigResolver tests using named arguments break cleanly with a TypeError; resolvers using positional args (none today) would need explicit attention.

- **Risk:** Removing the `overrides` column from `config_values` invalidates GIN-index queries that may exist in custom merchant code. Unlikely (no public API references the index), but the GIN index drop is technically a schema-DDL change.
  - **Mitigation:** Task 005's emitter no longer emits the GIN index. Merchants who relied on it (none expected) must add it back themselves to `config_value_overrides` if needed (where the lookup key is the composite PK anyway).

- **Risk:** Task 013's E2E test boots a long module chain (≥10 manifests). Any manifest with a changed shape since P4 silently breaks the test. Particularly, the `marko/database-pgsql` manifest's boot closure depends on `ProjectPaths` being available — if the test doesn't wire that, the storage binding fails on first DB call.
  - **Mitigation:** Task 013 starts from a fresh copy of `packages/catalog-market/tests/Feature/Tier3EndToEndTest.php` (which has already proven the long chain works) and extends the manifest list incrementally. Each addition is tested before moving on. The plan task includes the full manifest list.
