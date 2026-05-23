# Plan: Scoped Configs

## Created
2026-05-23

## Status
ready

## Objective
Create the `markommerce/config` interface package and `markommerce/config-pgsql` driver to provide developer-declared, merchant-overridable, per-property-scoped configuration values — built on top of the existing `markommerce/scope` primitives.

## Related Issues
none

## Discovery Notes

**Reuse from `markommerce/scope`** (no reinvention):
- `ScopeSignature` (axis-value serialization as `axis:value|axis:value`, sorted)
- `ScopeContext` (per-request mutable singleton with active axis paths)
- `ScopeRegistryInterface` (registered axes + hierarchies)
- `SignatureCandidateEnumerator` (enumerates candidate signatures by specificity)
- `#[Scoped(axes: [...])]` attribute on properties

**What's new:**
- `#[Config(key: '…', secret: false)]` attribute pairs with `#[Scoped]` on config-class properties
- Config storage is NOT entity-attached — it lives in a single shared table (`config_values`) with one row per config key
- Each row holds: global value + map of (signature → override value) + version (optimistic lock) + updated_at
- Codegen produces typed proxy subclasses (`*_Resolved`) using PHP 8.4 property hooks, so reads look like plain property access (`$cfg->welcomeMessage`)
- Code-level default changes happen exclusively via Marko Preferences (no separate defaults registry in v1)
- Secrets encrypted at rest via libsodium (`SodiumSecretCipher`) at the writer/resolver boundary
- Optimistic-locking write loop in `ConfigWriter` with bounded retries

**Greenfield**: no `Markommerce\Config\` namespace exists. Two new packages following the same shape as `scope` / `scope-pgsql`.

**Marko CLI conventions** (confirmed during devil's-advocate review): Marko has a real command framework — `Marko\Core\Command\CommandInterface` + `#[Marko\Core\Attributes\Command(name, description, aliases)]` + `Input`/`Output`. Commands are auto-discovered by `Marko\Core\Command\CommandDiscovery` from each module's `src/` directory; no explicit registration in `module.php`. Reference impls: `marko/database/src/Command/MigrateCommand.php`, `marko/queue/src/Command/*`. CLI tasks (017–019) conform directly — no exploration required.

**Marko Preferences mechanism** (confirmed during devil's-advocate review): preferences are declared via the `#[Marko\Core\Attributes\Preference(replaces: OriginalClass::class)]` attribute on the *replacement class* and discovered by `Marko\Core\Container\PreferenceDiscovery`. Resolution: `Marko\Core\Container\PreferenceRegistry::getPreference(string $original): ?string` (already follows chains and throws `PreferenceConflictException::circularPreference()` on cycles). The registry lives on the `Application` instance — it is NOT bound in the container by default; task 020's boot closure must register it as an instance.

**Config-class discovery mechanism** (rejected `marko/config` node approach during devil's-advocate review): Marko's `ConfigMerger` *replaces* list/numeric arrays on merge, so a shared list under `markommerce.config.classes` does not compose across modules. Replaced with attribute-scan discovery modeled on `CommandDiscovery` / `PreferenceDiscovery`: a `ConfigClassDiscovery` service scans each module's `src/` for classes with at least one `#[Config]` property. Result feeds `ConfigRegistryBuilder` at boot.

## Scope

### In Scope
- `markommerce/config` package: attribute, registry, resolver primitive, typed-proxy codegen, in-memory storage, writer with optimistic locking + write-time axis validation, request-scoped cache, libsodium encryption, CLI commands (`config:list`, `config:get`, `config:set`, `config:unset`, `config:generate`)
- `markommerce/config-pgsql` package: `config_values` table migration, PostgreSQL storage driver
- Strict type-casting on read (throw `InvalidConfigValueException` on mismatch)
- Marko-Preferences-driven default overrides at the app level
- READMEs for both packages
- Unit tests (Pest 4, fakes over mocks) and integration tests for the pgsql driver
- Documentation page at `docs/src/content/docs/packages/config.md` (auto-updated by post-implementation doc-updater pipeline)

### Out of Scope
- Admin UI / HTTP API surface (separate future package)
- Cross-request caching (APCu / Redis) — interface defined, no impl in v1
- Config history / audit log
- Defaults registry (`ConfigDefaults::set(…)` API) — Preferences only for now
- Import/export commands (`config:export`, `config:import`) — possible follow-up
- MySQL / SQLite drivers
- Demo module (`config-demo`) — separate effort
- Marko framework's existing `marko/config` (static env config) is unrelated; we don't touch it

## Success Criteria
- [ ] A developer can declare a config class with typed properties + `#[Config]` + `#[Scoped]` and read values via injected `CatalogConfig` (typed proxy)
- [ ] Reads observe the current `ScopeContext` and apply the same hierarchical fallback as `ScopeResolver` for entities
- [ ] A merchant (via `ConfigWriter` API) can set global values and per-scope overrides; both persist correctly in the single-row-per-key model
- [ ] An app integrator can change a package's config defaults using a Marko Preference on the config class (no DB write needed)
- [ ] Secret configs are encrypted at rest with libsodium; plaintext never appears in the `config_values` row
- [ ] Concurrent writes are serialized via optimistic locking; stale writes throw `StaleConfigWriteException` after bounded retries
- [ ] `config:generate` produces working typed proxy subclasses for every registered config class, including Preference-resolved subclasses
- [ ] All four CLI commands work against the PgsqlConfigStorage
- [ ] All tests pass under `composer test`; PHPStan level 8 clean; PHPCS clean
- [ ] Code coverage ≥ 80% in both packages

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `markommerce/config` package | - | pending |
| 002 | Exception classes | 001 | pending |
| 003 | `#[Config]` attribute | 001 | pending |
| 004 | `ConfigDefinition` + `ConfigRow` value objects | 001 | pending |
| 005 | `ConfigRegistry` + `ConfigRegistryBuilder` (reflection scan) | 002, 003, 004 | pending |
| 006 | `ConfigStorageInterface` + `InMemoryConfigStorage` | 002, 004 | pending |
| 007 | `ValueCaster` (strict cast / throw on mismatch) | 002, 004 | pending |
| 008 | `OverrideMatcher` (adapt scope walker to single-row JSON-map) | 002, 004 | pending |
| 009 | `ConfigWriter` (CRUD + optimistic lock + write-time axis validation; takes `SecretCipherInterface` as ctor dep with `NullSecretCipher` default) | 005, 006, 012 | pending |
| 010 | `ConfigResolver::resolved()` primitive (full ctor: registry, storage, matcher, caster, context, cipher, locator, prefRegistry) | 005, 006, 007, 008, 012 | pending |
| 011 | `ConfigCacheInterface` + `RequestConfigCache` + `ConfigCacheResetMiddleware` | 010 | pending |
| 012 | `SecretCipherInterface` + `SodiumSecretCipher` + `NullSecretCipher` stub | 002 | pending |
| 013 | Wire secret-branch behavior into `ConfigWriter` + `ConfigResolver` (no ctor changes — already wired in tasks 009/010) | 009, 010, 012 | pending |
| 014 | `ProxyGenerator` + `ProxyWriter` (codegen of `*_Resolved` subclasses) | 005 | pending |
| 015 | `ProxyLocator` + `ProxyAutoloader` + `PreferenceAwareScanner` (uses Marko's `PreferenceRegistry`) | 014 | pending |
| 016 | `ConfigResolver::get()` typed entry point (returns proxy instance) — ctor unchanged from task 010 | 010, 015 | pending |
| 017 | CLI: `config:list` + `config:get` (Marko `#[Command]` auto-discovery) | 010 | pending |
| 018 | CLI: `config:set` + `config:unset` (Marko `#[Command]` auto-discovery) | 009 | pending |
| 019 | CLI: `config:generate` (Marko `#[Command]` auto-discovery) | 014, 015 | pending |
| 020 | `module.php` final wiring (bindings, singletons, `PreferenceRegistry` instance, `ConfigClassDiscovery`, lazy `SecretCipher`) | 011, 013, 016, 017, 018, 019 | pending |
| 021 | Scaffold `markommerce/config-pgsql` package | 006 | pending |
| 022 | `config_values` table migration + schema emitter | 021 | pending |
| 023 | `PgsqlConfigStorage` implementation + integration tests | 021, 022 | pending |
| 024 | README for `markommerce/config` | 002–020 | pending |
| 025 | README for `markommerce/config-pgsql` | 021, 022, 023 | pending |

## Architecture Notes

**Storage row shape (`config_values`):**
```
config_key      VARCHAR(255) PRIMARY KEY
value           JSONB              -- global value, JSON-encoded
overrides       JSONB              -- {"axis1:val|axis2:val": <jsonValue>, ...}
version         INTEGER  DEFAULT 0  -- optimistic lock counter
updated_at      TIMESTAMPTZ
```

**Override key format**: matches `ScopeSignature::toString()` output (sorted `axis:value|axis:value`). Lets us reuse `SignatureCandidateEnumerator` directly when iterating fallback candidates.

**Resolution layers** (low → high priority):
1. Class property PHP default (potentially swapped via Marko Preference)
2. DB row `value` column (merchant-set global)
3. DB row `overrides` entry matching current `ScopeContext` (most-specific signature wins)

**Codegen output**:
- Located at `<app-root>/var/generated/config-proxies/` (the root `.gitignore` already lists `var/`, so no additional gitignore changes needed)
- FQN: `Markommerce\Config\Generated\<OriginalNamespace>\<Class>_Resolved`
- Uses PHP 8.4 property hooks (not magic methods) so it complies with the project's "No magic methods" rule
- Generated for every registered config class AND every Preference-resolved subclass (Preference must be a subclass of the original or codegen rejects with `InvalidConfigClassException::nonSubclassPreference`)

**Config-class shape constraints** (enforced at registry build time, task 005):
- Must NOT declare a constructor with required (non-defaulted) parameters
- Every `#[Config]` property must have a default value OR be nullable
- No union/intersection/readonly property types
- Validation happens before codegen so failures surface early

**Optimistic locking flow** (in `ConfigWriter`):
```
attempt 1..N:
    row = storage.load(key)              // returns version too
    mutate row (set/unset global or override)
    success = storage.compareAndSave(key, row, expectedVersion=row.version)
    if success: return
throw StaleConfigWriteException::forKey(key, retries)
```

Default `N = 3`. Failed writes raise loudly with `message`, `context`, `suggestion`.

**Storage `compareAndSave` cases** (both `InMemoryConfigStorage` and `PgsqlConfigStorage` must honor):
- Empty row (`value === null && overrides === []`) against existing row at version V: DELETE WHERE version=V; success = affected_rows === 1
- Empty row against absent key with `expectedVersion === 0`: idempotent no-op, return true (prevents writer retry-loop exhaustion on already-unset values)
- Non-empty row, `expectedVersion === 0`: INSERT ON CONFLICT DO UPDATE WHERE existing.version=0 (creates new or replaces a brand-new row only)
- Non-empty row, `expectedVersion > 0`: must atomically UPDATE WHERE version=expectedVersion AND return false if the row no longer exists (do NOT silently INSERT a brand-new row when the caller's read was based on a row that's since been deleted)

**Secret handling**: `ConfigWriter` consults `ConfigRegistry` for the property's `secret` flag; if true, value is encrypted with `SecretCipher` *before* being JSON-encoded into either `value` or an `overrides` entry. `ConfigResolver` decrypts symmetrically before casting.

**Type casting**: `ValueCaster` casts decoded JSON to the property's declared type (string/int/float/bool/array/enum). On mismatch (e.g., DB stored `"hello"` for `int $itemsPerPage`), throws `InvalidConfigValueException` with the offending key, raw value, and target type.

## Risks & Mitigations

- **Risk**: PHP 8.4 property hooks in generated proxies fail under unusual class shapes (readonly properties, asymmetric visibility, intersection types, parent constructor with required args, non-nullable property with no default). **Mitigation**: `ConfigRegistryBuilder` (task 005) and `ProxyGenerator` (task 014) both validate; reject loudly with `InvalidConfigClassException` factories naming the specific reason (`configClassHasRequiredConstructor`, `propertyMissingDefaultOrNullability`, etc.). Failures happen at registry build, not at runtime read.
- **Risk**: Preference-resolved subclasses missed during codegen → `ProxyNotGeneratedException` at runtime. **Mitigation**: `PreferenceAwareScanner` consults the populated `PreferenceRegistry` at generate time; `config:generate` exits non-zero on missing proxies and is enforced in CI before PHPStan. Additionally, `ConfigResolver::get()` validates that the preferred class IS a subclass of the requested class (loud `InvalidConfigClassException::nonSubclassPreference` otherwise).
- **Risk**: Libsodium key not configured in app environment. **Mitigation**: `SecretCipherInterface` binding is a lazy closure that constructs `SodiumSecretCipher` only on first invocation. A `NullSecretCipher` default stub throws `SecretCipherException::notConfigured()` on call. CLI commands that never touch secrets (e.g., `db:migrate`, `config:generate`) work fine; only the first secret encrypt/decrypt fails loud with the env-var name to set.
- **Risk**: Optimistic locking starves writes under contention. **Mitigation**: capped retry count (3) + loud failure on overflow; configs are written rarely so realistic contention is extremely low.
- **Risk**: PgSQL JSONB UPDATE without indexing causes slow lookups for "all configs with override on axis X". **Mitigation**: add GIN index on `overrides`; not used for resolver reads (always lookup by primary key) but available for admin tools.
- **Risk**: Naming collision with Marko framework's `marko/config` (static env config). **Mitigation**: namespace differs (`Markommerce\Config\` vs `Marko\Config\`); README opens with a "this is not marko/config" callout.
