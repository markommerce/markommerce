# Devil's Advocate Review: scoped-configs

## Critical (Must fix before building)

### C1 — Task 015 uses a non-existent "preference map"; Marko Preferences are attribute-driven, not declared in module.php

**Affected tasks**: 015, 016, 020, 019.

Task 015 says `PreferenceAwareScanner::expand(list<class-string> $registeredConfigClasses, array $preferenceMap)` and that "Marko's preference map shape: usually `interfaceOrAbstractClass => concreteClass`". This is wrong. Marko has no module.php preference map. Preferences are declared with the `#[Preference(replaces: OriginalClass::class)]` attribute on the *replacement class*, and are discovered at boot by `Marko\Core\Container\PreferenceDiscovery` scanning module src directories. They are stored in `Marko\Core\Container\PreferenceRegistry` which:

- exposes `getPreference(string $original): ?string` (already follows chains and throws `PreferenceConflictException::circularPreference()` on cycles)
- is held on `Application::$preferenceRegistry` (`public private(set)`) but **is not bound in the DI container** — only the Application has a direct reference. The Container holds it privately for resolve-time swapping.

Consequences if not fixed:
- The scanner can't be built against a nonexistent argument; the worker on task 015 will guess.
- Task 020 cannot just bind `PreferenceResolverInterface` to "Marko's actual preference resolution" without first registering `PreferenceRegistry` as a container instance.
- Cycle detection in task 015 duplicates work `PreferenceRegistry` already does.

Fix (applied below): replace the "preference map" argument with a `PreferenceRegistry` dependency, drop the cycle-detection requirement from task 015, and add an explicit step in task 020 to register `PreferenceRegistry` as a container instance before booting `markommerce/config` so the resolver/scanner can resolve it.

Also: task 015 must enforce / document that for configs the preferred class **must extend** the original config class (otherwise the codegen subclass `extends $preferred` will not satisfy the typed return of `ConfigResolver::get(Original::class): Original`). Marko's `PreferenceRegistry::register()` does not enforce this — it allows arbitrary swaps.

---

### C2 — Task 020's `markommerce.config.classes` registration mechanism does not compose

**Affected tasks**: 020, 024.

Marko's `ConfigMerger` (`marko/config/src/ConfigMerger.php`) deep-merges *associative* arrays but **replaces lists**. The merger explicitly checks `isAssociative()` and falls through to overwrite for numeric / list arrays. So if `markommerce/config` ships `config/markommerce.php` returning `['config' => ['classes' => []]]` and `markommerce/catalog` ships its own `config/markommerce.php` with `['config' => ['classes' => [CatalogConfig::class]]]`, the merge would work only because the first is empty; if two domain modules both ship a non-empty `classes` list, the later one overwrites the earlier (priority is vendor → modules → app, alphabetical within source, so behavior is order-dependent and silent).

Consequence: downstream modules (catalog, cart, checkout, …) cannot all contribute config classes simultaneously through `markommerce.config.classes`. The user-facing "registration ergonomics" the plan claims do not work.

Fix (applied below): replace the "config classes come from a `marko/config` node" approach with discovery via a `#[Config]` attribute scan over module `src/` directories at boot, matching how Marko discovers commands, preferences, and plugins. This is consistent with the rest of the framework and is composition-safe. Add a `ConfigClassDiscovery` task component.

If discovery scanning is deemed too heavy for the first cut, the alternative is to have each module's `module.php` return a `'configClasses' => [...]` key that `markommerce/config` reads from each `ModuleManifest`. That would require a Marko framework change — out of scope here. Discovery scan is the right choice.

---

### C3 — Task 014's generated proxy constructor will collide with the parent's constructor

**Affected tasks**: 014, 016.

Task 014 says: "The generated class declares a constructor taking `ConfigResolver $__resolver` (single dependency)." But the proxy `extends` the user's config class. If the user's `CatalogConfig` has no constructor, that works. If `CatalogConfig` declares a constructor (even an empty one, or with required deps), the subclass constructor must be compatible OR call `parent::__construct(...)`.

Furthermore: Marko's Container resolves dependencies by reflecting on the constructor. If the proxy is instantiated via `new $proxyClass($this)` (as task 016 shows), that's fine — but if anything ever fetches the proxy via the container by FQN, the container will try to autowire `$__resolver` (a `ConfigResolver` — resolvable) but will also fail if the parent has required constructor args that the proxy doesn't surface.

Fix (applied below):
- Task 014: explicitly require that target config classes have **no constructor with required parameters**. Add this to the registry-time validation in task 005 (otherwise codegen fails late).
- Task 014: validate at codegen time and throw `InvalidConfigClassException` with guidance ("config classes must be plain DTO-shaped; constructor injection is not allowed on a `#[Config]` class").
- Task 016 fixture proxy must reflect the same convention.

Also: hooked-only properties have no backing storage, so the parent's declared default (`public string $welcomeMessage = 'hello';`) is fine because we read it at registry-build time via reflection BEFORE the proxy subclass is loaded. But the subclass's property redeclaration *must* use the IDENTICAL type as parent (no covariance allowed on properties at PHP 8.4). Task 014 already says "preserves the original property's declared type" — keep that strict.

---

### C4 — Task 010 introduces `ScopeContext` as a constructor dep but task 016 adds two more — without telling task 010

**Affected tasks**: 010, 013, 016.

Task 010 lists ConfigResolver's constructor as: `ConfigRegistry, ConfigStorageInterface, OverrideMatcher, ValueCaster, ScopeContext`. Task 013 adds `SecretCipherInterface`. Task 016 adds `ProxyLocator` and `PreferenceResolverInterface`. Each task says "update the previous task's tests" but a worker building task 010 has no way to know future signatures. A worker building task 013 after task 010 is merged will *modify* the constructor and have to update task 010's tests too — but the task description in 013 is too vague ("update existing tests from tasks 009 and 010 — they should now inject a real `SodiumSecretCipher` with a fixed test key, or a deterministic `IdentitySecretCipher` test double"). Task 016 is even vaguer: "Update task 010's tests to thread these through (identity preferences + a fake locator)."

Consequence: a worker on task 013 will either skip the test updates or get blocked on understanding what the test scaffolding needs to look like. Likewise 016.

Fix (applied below):
- Task 010: define `ConfigResolver`'s constructor with all FINAL dependencies upfront (including `SecretCipherInterface` and `ProxyLocator` and `PreferenceRegistry`) but stub the not-yet-implemented dependencies behind null-object defaults (`NullSecretCipher` that throws on use, `NullProxyLocator`). This way task 013 and 016 swap stubs for real implementations rather than restructuring the constructor.
- Task 013: scope of work clarified to "swap the NullSecretCipher binding for SodiumSecretCipher in module wiring (task 020) and add tests covering the secret branch." The constructor doesn't change.
- Task 016: same — swap NullProxyLocator stub for ProxyLocator, add `get()` method, no constructor surgery.

Alternative (rejected): leave incremental constructor evolution as is. This would force each subsequent task to handle merge-state from the previous tasks' tests, multiplying coordination cost.

---

### C5 — InMemoryConfigStorage on empty-mutation against an absent row will hang in the retry loop

**Affected tasks**: 006, 009, 023.

The writer flow: `setOverride` → load (returns null) → build blank row with version=0 → apply withOverride → call `compareAndSave`. If the override map was empty before and stays empty (e.g., user called `unsetOverride` on a signature that was never set), `compareAndSave` will see `value === null && overrides === []` and per the task 006 contract delete the row. But the row doesn't exist. Storage will return `affected_rows === 1`? No — delete affects 0 rows, returns false. Writer retries 3 times, throws `StaleConfigWriteException`. That's wrong — the operation was idempotent and should have been a no-op success.

Fix (applied below): task 006 (InMemoryConfigStorage) and task 023 (PgsqlConfigStorage) both add the special case: "If row would be empty (`value === null && overrides === []`) and `expectedVersion === 0` and the row does not currently exist, return `true` as a no-op success." Task 009's writer doesn't need to know — the storage handles it.

Add a test case to both: `it returns true from compareAndSave for an empty mutation against an absent row`.

---

### C6 — Task 020's boot-time secret-cipher validation will break unrelated CLI commands

**Affected tasks**: 020, 012.

Task 020 says: "If any registered config has `secret: true`, attempt to resolve `SecretCipherInterface` — if binding throws (missing key), raise a setup exception with the env-var name to set."

This runs at boot for every CLI invocation, including `config:generate`, `config:list`, and unrelated commands like `db:migrate`, `route:list`, etc. A developer running `db:migrate` on a fresh dev environment with secrets declared but `MARKOMMERCE_CONFIG_SECRET_KEY` unset will be blocked by an unrelated package. The plan flags this as a risk but doesn't apply a fix.

Fix (applied below): change the boot validator to a "lazy" validator on the resolver/writer secret path. Specifically, `SecretCipherInterface` binding remains a closure that throws on first invocation if the key is unset; `ConfigResolver` and `ConfigWriter` only invoke it when a secret config is actually accessed. This way `db:migrate` etc. work fine; only the first read/write of an encrypted config fails loud.

Task 012's `SodiumSecretCipher` already validates the key in its constructor — so we keep the binding as a lazy closure. The constructor of the resolver/writer should accept `SecretCipherInterface` but should NOT instantiate it until a secret read/write actually happens. Inject as `Closure(): SecretCipherInterface` or a small `SecretCipherProvider` indirection.

---

### C7 — `markommerce/config-pgsql` task 023 deletes via `DELETE WHERE config_key=? AND version=?` but the row could have been replaced under us

**Affected tasks**: 023.

The delete path is: "If `$row->value === null && $row->overrides === []`: `DELETE FROM config_values WHERE config_key = ? AND version = ?` → return `affected_rows === 1`". Consider two writers:
- A reads version=5, mutates to empty.
- B reads version=5, mutates to add an override.
- B commits first → row now version=6.
- A tries DELETE WHERE version=5 → affects 0 rows → returns false. Retry: A reloads, sees version=6 with overrides, mutates to remove its own change (a noop) — empty? No, B's override is still there. Should A delete the row? No — B's override means the row should stay.

Actually the writer logic in task 009 says "mutation results in an empty row → compareAndSave deletes." The above scenario means A's *retry* re-runs the mutation on B's row, and if the mutation (e.g., unsetOverride for a signature that's not in B's row) makes a noop, the row stays non-empty, A calls compareAndSave with a non-empty row — that becomes an INSERT … ON CONFLICT UPDATE under the version check. OK, the retry logic handles this fine.

So C7 is **not** a bug in C7's narrow framing, but it exposes that the integration test in task 023 must include the case `it serializes concurrent writes to the same key where one writer empties the row and another writer adds an override`. Otherwise the multi-step delete-vs-update race won't be tested.

Fix (applied below): add a specific concurrency requirement to task 023.

---

## Important (Should fix before building)

### I1 — Task 011 cache lifecycle is unclear; risk of cross-request leak in production

**Affected tasks**: 011, 020.

Marko has no request scope in the container. If `RequestConfigCache` or `CachingConfigStorage` is registered as a singleton in `module.php`, cached values persist across requests — the cache becomes a *process* cache. The plan describes the cache as request-scoped but doesn't wire a reset mechanism.

Fix (applied below):
- Task 011: add a `clear(): void` method on `ConfigCacheInterface` (and `RequestConfigCache`).
- Task 020: add a small `ConfigCacheResetMiddleware` (similar to scope's `ScopeResolutionMiddleware`) registered as `globalMiddleware` that calls `$cache->clear()` at the start of each request.
- Document that in CLI usage the cache is irrelevant (single-process per invocation) but the reset middleware is still safe to register.

### I2 — Task 005's "axis exists in ScopeRegistry" check happens at *build* time, but registry might not be hydrated yet at boot

**Affected tasks**: 005, 020.

Task 005 builder calls `ScopeRegistryInterface::hasAxis($axis)` for each declared axis. Task 020 boot ordering says "Build the ConfigRegistry at boot from configured config classes." For this to work, `ScopeRegistryInterface` must already be bound and hydrated when `markommerce/config`'s boot runs.

Scope's `module.php` binds `ScopeRegistryInterface` as a closure that reads from `ConfigRepositoryInterface` (`scope.axes`), and scope's own boot calls `$registry->listAxes()` etc. Both modules' boots run inside Marko's loop with no explicit ordering guarantees other than dependency-resolved order.

Verify: `markommerce/config`'s `composer.json` requires `markommerce/scope` (task 001 says so). Marko's `DependencyResolver` orders boot calls based on composer deps. So scope boots first. Good.

But also: tests for task 005 must not depend on the boot order — they should construct a `FakeScopeRegistry` directly. Task 005 says exactly that. Good.

No code change required, but add a note to task 020 acceptance criteria: "verify `markommerce/scope` boot has completed before `markommerce/config` boot via composer dependency resolution; integration test asserts that `ConfigRegistry` build succeeds when `ScopeRegistry` is fully wired."

### I3 — Task 014 doesn't account for `enum`-typed properties in generated source

**Affected tasks**: 014, 007.

Task 007 ValueCaster supports backed enums (`StringBackedEnum` / `IntBackedEnum`). The proxy generator (task 014) says "Property type comes from `ConfigDefinition::$type` (already normalized in task 005)." For an enum-typed property like `public Color $themeColor = Color::Blue;`, the generated source must produce:

```php
public \App\Color $themeColor {
    get => $this->__resolver->resolved(\OriginalCls::class, 'themeColor');
}
```

The hook's return type is the FQN of the enum, but `ConfigResolver::resolved()` returns `mixed`. PHP property hooks DO enforce return-type covariance on `get`. The generated property's effective return type is `\App\Color`, and `mixed` is not covariant with that — so PHP will throw `TypeError` on a get that returns a non-`\App\Color` value. Since `ValueCaster` actually casts to the enum, the runtime value IS a `Color`, but the static type checking and the property hook contract are fine. PHP only checks the actual value, not the declared return type of the source.

Edge case: what about nullable types? `public ?string $welcomeMessage = null;`. The property hook would be `public ?string $welcomeMessage { get => $this->__resolver->resolved(...); }`. ValueCaster returns null fine. OK.

Issue: task 014 doesn't enumerate what happens if the property has NO declared default value. PHP requires a typed property to be initialized before read unless nullable. With a hooked property, the parent's default initialization is bypassed. If the property has no default and isn't nullable, but it's also hooked-only in the subclass, then `defaultValue` from `ReflectionProperty::getDefaultValue()` returns null on the parent — but the property could still be required. Resolver path: row null, definition.defaultValue null, but the property is non-nullable → resolver returns null → PHP's property hook get returns null → TypeError at access.

Fix (applied below):
- Task 005: validate that every `#[Config]` property has either a default value OR is nullable. If neither, throw `InvalidConfigClassException` (extend task 002 exception factory accordingly).
- Add a test in task 005: `it throws InvalidConfigClassException when a #[Config] property has no default and is not nullable`.

### I4 — Tasks 017/018/019 CLI discovery is treated as research; framework expert input avoids the rabbit hole

**Affected tasks**: 017, 018, 019.

Tasks 017–019 say "IMPLEMENTER MUST first `grep -r 'Command' ../marko` from the workspace root to find Marko framework's command base class / interface and the registration pattern." This is unnecessary research — the answer is already known:

- `Marko\Core\Command\CommandInterface` — single `execute(Input $input, Output $output): int` method
- `Marko\Core\Attributes\Command(name: '...', description: '...', aliases: [...])` — class-level attribute
- `Marko\Core\Command\Input` and `Marko\Core\Command\Output`
- Auto-registration: `Marko\Core\Command\CommandDiscovery` scans module `src/` directories for classes with `#[Command]` attribute. **No registration in `module.php` is needed**.
- Reference impls: `marko/database/src/Command/MigrateCommand.php`, `marko/queue/src/Command/*`, `marko/page-cache/src/Command/*`.

Fix (applied below): replace the "implementer must grep" instruction with concrete API references. Remove the "fallback to standalone PHP scripts under bin/" — that's no longer needed because Marko has the command framework.

Also: remove task 020's claim "Register the four commands so they're invokable via the Marko CLI" — they auto-register from `#[Command]`. Update task 020 accordingly.

### I5 — Task 020 lists testing requirement "registers the five commands" but auto-discovery makes that untestable as a binding

**Affected tasks**: 020.

The acceptance test "registers the config:list, config:get, config:set, config:unset, and config:generate commands" cannot be tested via module.php inspection — they're discovered via `#[Command]` attribute scan. Test must instead verify the commands exist under `packages/config/src/Command/` and have the `#[Command]` attribute with the expected name.

Fix (applied below): rephrase the requirement.

### I6 — No task for adding `var/generated/config-proxies/` to `.gitignore`

**Affected tasks**: 014, 015.

The root `.gitignore` already has `var/` (line 2), so `var/generated/config-proxies/` is implicitly covered. **No action needed.** The plan was concerned about it, but it's already handled.

Add an acceptance criterion in task 014: "verifies `var/` is already gitignored at repo root; no additional gitignore changes needed."

### I7 — No task for path-repos / root composer.json updates

**Affected tasks**: 001, 021.

The root `composer.json` uses `"url": "packages/*"` (path repository), so any new package under `packages/` is automatically discovered by composer. **No action needed** unless we want to explicitly require the new packages from the root's `require` block.

The plan's task 001 mentions this in context but doesn't make it a requirement. Add: task 001 must add `markommerce/config: self.version` to the root composer.json's `require` (matching how scope/scope-pgsql/etc. are listed). Same for task 021 with `markommerce/config-pgsql`.

### I8 — Task 023's expected-version-on-insert semantics need spelling out

**Affected tasks**: 023.

The plan says "for a brand-new row (`$expectedVersion === 0`), the INSERT succeeds and bumps version to 1." But the SQL needs to handle these cases atomically:
- `$expectedVersion === 0` and row exists: must NOT overwrite. INSERT … ON CONFLICT DO UPDATE WHERE `config_values.version = 0` — the WHERE clause must fail for any row with version > 0. Good, the SQL design handles it.
- `$expectedVersion === 5` and row doesn't exist: INSERT succeeds with version=1. But the writer expected version 5 to be the prior. The SQL would happily create version=1, but that's the wrong outcome — the writer was operating on a stale assumption.

The latter is a subtle race: writer loads row at version 5, another process deletes it (e.g., empty-row cleanup), writer tries compareAndSave with `expectedVersion=5`, INSERT creates version=1. From the writer's perspective, success. But the row's content is now what writer A composed — which was based on B's deleted state — which… might or might not be correct.

Fix (applied below): add a requirement to task 023 that `compareAndSave` distinguishes INSERT (new row) from UPDATE (existing). If `$expectedVersion > 0` but no existing row, the call must return `false` (stale). The SQL needs adjustment — either:
- Two-step: first try UPDATE WHERE version=$expectedVersion; if affected=0 AND $expectedVersion === 0, do INSERT; else return false.
- Or use a CTE that branches on the prior-version check.

Either is fine; the two-step approach is simpler and is what we apply.

### I9 — Task 002 listing of exception types misses several

**Affected tasks**: 002, 012, 015.

The plan's task 002 enumerates seven exception classes but tasks 012 (`SecretCipherException` per task 012's own note), 015 (cycle / autoloader errors), and 023 (DB integration errors) reference more. Task 012 even notes "extend task 002's exception set if so."

Fix (applied below): expand task 002 to include `SecretCipherException` and an explicit `ProxyAutoloaderException` (or fold autoloader errors into `InvalidConfigClassException`). Add a requirement to task 002 covering each.

### I10 — Tests for task 023 require a Postgres setup that's not described

**Affected tasks**: 023, 022.

The plan says "Integration tests use a real PostgreSQL test database — pattern match `scope-pgsql`'s `tests/Feature/Helpers/`". `packages/scope-pgsql/` only has two files (`PgSqlScopedFieldRenderer.php` and `ScopesGinIndexEmitter.php`) — no `tests/Feature/Helpers/` exists yet. So there's no helper to mirror.

Fix (applied below): task 023 should explicitly build a small connection-helper for the test bootstrap, modeled on `marko/database` integration tests (the framework does have them). Also add tagging: integration tests must be `->group('integration-destructive')` per `testing.md`.

### I11 — Task 005 doesn't propagate `axes` length / multi-axis support consistently

**Affected tasks**: 005, 008, 009.

`#[Scoped(axes: [...])]` from scope can declare multiple axes. The OverrideMatcher (task 008) handles this via `SignatureCandidateEnumerator`. ConfigWriter (task 009) validates that override signature axes are a subset of the declared axes. Good — but `setOverride` should also reject signatures whose axes are a STRICT SUBSET when the property has multi-axis required? E.g., `axes: ['locale', 'market']` — is `locale=en` alone a valid override, or must both axes be present?

Scope's enumerator (line 51 of `SignatureCandidateEnumerator`) builds signatures via cartesian product including OMIT (null) per axis. So partial-axis signatures ARE expected as candidates. So `setOverride` should accept partial-axis signatures as long as each axis in the signature is in the declared set. The plan's wording "validates each axis name in `$signature` against `$definition->axes`" is consistent with this.

No fix needed but the test "rejects orphan overrides" in task 009 should clarify what an "orphan" override is. Done in applied edits.

---

## Minor (Nice to address)

### M1 — Task 003 says "rejects an empty key string with a clear exception at construction time" — what exception?

Probably `InvalidArgumentException` rather than a domain exception, but the plan doesn't say. Either decision is fine; just pick one.

### M2 — Task 007's "object value types out of scope" is fine for v1, but `DateTimeImmutable` is a common config type (e.g., a `maintenanceWindowStart`)

Document this limitation in task 024 README explicitly. Users will hit it.

### M3 — Task 022's `config_key VARCHAR(255)` may collide with very long keys

255 chars is plenty, but document the limit in the README. PG's `TEXT` type would avoid this with no perf cost; consider it.

### M4 — Task 011's `RequestConfigCache` interface signature `get(string $key, callable $loader): ?ConfigRow`

PHPStan level 8 will complain about `callable` — use `Closure(): ?ConfigRow` for stricter typing.

### M5 — Task 014's tokenizer parsing test ("PHP can parse the generated source")

A simpler equivalent: `require` the generated file inside a test (with a unique class name per test) and assert `class_exists($fqn)`. Avoids tokenizer fragility.

### M6 — Task 023 should add an explicit unique constraint check / handle the (very unlikely) case where two INSERTs race

The `INSERT … ON CONFLICT (config_key)` handles this — PG serializes via the primary key — so no fix needed, but the test should explicitly exercise the "two simultaneous INSERTs to a brand-new key" case in addition to the update-update race.

### M7 — Task 024 README test approach (Pest tests for markdown sections)

Other markommerce packages may not have this convention yet. Don't enforce; use plain shell / file_exists checks in tests. (Already noted in task — "if any exists".)

### M8 — `ConfigDefinition::$type` storing types as strings ("int", "string", FQN for enums)

Consider an enum or sealed value-object for the type instead, but a string is fine for v1.

---

## Questions for the Team

### Q1 — Should "set null as a global" be possible distinct from "unset global"?

Currently `value === null` means "no global" (default-falls-through). If a user wants to set null as a meaningful global value for a nullable property, the model can't distinguish. Decision needed.

### Q2 — Should `config:get --scope=axis=value` mutate the live `ScopeContext` or use a temporary one?

Task 017 leaves this open. If it mutates live context, a long-running CLI process (unlikely) would have side effects. If it builds a synthetic context just for the command run, that's cleaner but requires `ConfigResolver` to support `resolvedAt(ScopeContext)` overload. Recommend: synthetic context, new method. Confirm.

### Q3 — Should the preferred config subclass be required to extend the original, or can it be an unrelated class implementing the same shape?

Marko's `PreferenceRegistry` does not enforce inheritance. For config proxies, codegen extends the preferred class. If a Preference points to a wholly unrelated class, `ConfigResolver::get(CatalogConfig::class)` returns an instance of `AppCatalogConfig_Resolved` that does NOT `instanceof CatalogConfig` — PHPDoc lies. Recommend: enforce in `PreferenceAwareScanner::expand()` that the replacement is a subclass of the original; throw `InvalidConfigClassException` otherwise.

### Q4 — Is `config:generate` expected to run inside Docker dev only, or also on a production deploy?

CI policy says generate before PHPStan — fine in dev. In production, do we ship the generated files in the deploy artifact, or do we run `config:generate` post-deploy? Affects how the autoloader handles a missing directory.

### Q5 — Caching wrapper (`CachingConfigStorage`) vs cache-aside in `ConfigResolver`

Task 011 picks "shape 1 — cache around the row read." But the resolver still re-runs `OverrideMatcher` + `ValueCaster` on every property access even when the row is cached. For a request that reads 50 config properties, that's 50 cast operations. Cheap, but not free. Consider value-level memoization too. Out of scope decision.

---

## Summary of Applied Fixes (Critical + Important)

Below changes are applied directly to the plan files in this same commit.
