# Devil's Advocate Review: scope-composite-overrides

Performance is a hard constraint and the user has accepted a clean breaking change. The review focuses on (a) places the algorithm or its tooling silently degrades to O(N) over stored overrides, (b) framework primitives the plan assumes exist but do not, and (c) cross-task contract gaps that will block parallel workers.

## Critical (Must fix before building)

### C1. `selectRaw` / `whereRaw` do not exist on `QueryBuilderInterface` — Task 013 is unbuildable

Task 013 ("ScopedSelect" + "ScopedWhere") plans to call `$builder->selectRaw($sql . ' AS "alias"')` and `$builder->whereRaw($sql . ' ? ', [$value])`. Neither method exists on `Marko\Database\Query\QueryBuilderInterface` (only `orderByRaw`, `raw`, `where`, `whereIn`, `whereNull`, `whereNotNull`, `whereJson*`, `having`, `select`).

This is a hard blocker. Three options, in order of "least invasive to this plan":

1. **Drop `ScopedSelect` / `ScopedWhere` from the plan.** Keep `ScopedOrderBy` only (it's the existing functionality and the only thing the renderer was originally built for). File a follow-up plan to add `selectRaw`/`whereRaw` to `marko/database` first, then add the scoped query specs in a future plan. **Recommended** — keeps this plan focused on what it set out to do (multi-axis composite resolution), avoids cross-package scope creep.
2. Add `selectRaw`/`whereRaw` to `marko/database`'s `QueryBuilderInterface`. Cross-package change, not in scope of this plan per the brief's "Out of Scope" list ("Anything outside the `packages/scope/` and `packages/scope-pgsql/` directories").
3. Implement `ScopedSelect`/`ScopedWhere` as exotic uses of `having()` and `raw()`. Hacky and breaks the query-builder abstraction.

**Applied fix**: Option 1 — drop `ScopedSelect`/`ScopedWhere` from task 013 and from `_plan.md`'s in-scope list. Rename task 013 to "ScopedOrderBy rewrite". Drop the corresponding integration tests in task 014. Documentation tasks 009/015 update accordingly.

### C2. No GIN index support in Marko's schema — Task 012 over-claims the runway

Plan claims `Marko\Database\Schema\IndexType` supports `INDEX` / GIN and that "the marko codebase already supports the GIN index type". It does not.

- `IndexType` enum only has `Btree`, `Unique`, `Fulltext`. No `Gin`, no `Hash`, no operator class support.
- `Schema\Index` has `name, columns, type` — no operator class field.
- `Attributes\Index` has `name, columns, unique` — no type, no operator class. Targets class only, not properties.
- `PgSqlGenerator::generateAddIndex()` only handles `Unique` vs everything-else as plain B-tree. It does not emit `USING GIN` or operator classes.
- `PgSqlIntrospector::getIndexes()` always returns `IndexType::Btree` or `Unique`, never anything else, so a GIN index in the database round-trips to `Btree` in the diff calculator.
- `SchemaBuilder::buildIndex()` always builds `Btree` or `Unique` from `IndexMetadata`.

The plan claims "rely on `DiffCalculator`'s existing idempotency" — that works **only because** `findIndexesToAdd`/`findIndexesToDrop` match by index name only (verified in `DiffCalculator::findIndexesToAdd`). So name-based idempotency is fine, but emitting GIN at all requires upstream code changes.

**Applied fix**: Reframe task 012 to use the schema-extender / post-migration-hook path (option 2 in the task's "Context"). Specifically, the auto-migration in `markommerce/scope-pgsql` registers a separate side-channel that emits a raw `CREATE INDEX IF NOT EXISTS "<table>_scopes_gin" ON "<table>" USING GIN ("scopes" jsonb_path_ops)` statement per HasScopes-bearing table, executed at schema-apply time. Idempotency comes from `IF NOT EXISTS` at the SQL level, not from `DiffCalculator`. This is fully contained in `scope-pgsql` and does not require upstream Marko changes. Update the task to make this the only path and remove the trait-`#[Index]` alternative which is not viable.

Also update the task requirements to reflect this approach: no diff-calculator no-op test (it's bypassed), instead test that the raw `CREATE INDEX IF NOT EXISTS` SQL is emitted alongside the normal schema apply for HasScopes-bearing tables.

### C3. `DatabaseConfig` cannot be constructed from env vars — Task 014's helper is broken

Task 014's `PostgresTestConnection` helper plans to "build a `DatabaseConfig` (per `Marko\Database\Config\DatabaseConfig`)" from `getenv('DB_HOST')` etc. But `DatabaseConfig`'s only constructor takes a `ProjectPaths` and requires a `config/database.php` file. There is no path to construct it from env vars directly.

**Applied fix**: Task 014's helper bypasses `DatabaseConfig` entirely. The helper constructs a `PDO` directly from env vars and wraps it via a test-only subclass of `PgSqlConnection` that overrides `createPdo()` (already a protected hook on `PgSqlConnection` — see lines 69-76). Or, equivalently, writes a temporary `config/database.php` to a tmp dir and uses `ProjectPaths` pointed at it. Pick the PDO-override path (simpler, no fs side effect, isolated to test scope).

Task 014's requirements also need: "the helper opens a real PDO connection via a `PgSqlConnection` subclass that overrides `createPdo` to bypass the `config/database.php` requirement".

### C4. `walkAt` axis-mismatch behavior contradiction (test breakage)

Task 005's pseudo-code says: "if axisName not in attributeAxes: return notFound()". This matches the existing test `'returns notFound via walkAt when the axis does not match'` and is correct.

However, task 005's stated rewrite implementation **also** says, in the validator path (task 003), that `setOverride` rejects signatures with axes not in attribute axes. The walkAt path correctly does NOT call the validator (writes only), so this is consistent.

But task 005's Requirements include `'walkAt with a single-axis signature returns notFound when the axis is not in the attribute axes'` — matching current behavior. Good.

What's missing: the existing test `'returns notFound via walkAt when the axis does not match'` lives in `ScopeWalkerTest.php` and uses `new Scope('locale', 'de')`. Task 005 says "KEEP the assertions; only update the construction" — but task 005 only lists "All four existing `walkAt` tests" in its Acceptance Criteria. There are actually **four** existing walkAt tests (`resolves`, `walks hierarchy ancestors`, `returns notFound via walkAt when the axis does not match`, plus the indirect "with HasScopesInterface" ones — five total). The "four" claim is approximate; the task body should require updating *all* tests that reference `new Scope(`. The grep is more reliable than a count.

**Applied fix**: Update task 005's Acceptance Criteria from "All four existing walkAt tests" to "All existing walkAt tests (currently three direct walkAt tests in ScopeWalkerTest.php) updated to construct ScopeSignature instead of Scope. Verified via `grep -n 'new Scope(' packages/scope/tests/Unit/Resolution/ScopeWalkerTest.php` returning zero hits after task completion."

### C5. `ScopeWalker` constructor signature change breaks every existing test that does `new ScopeWalker()` — task 004 doesn't list them

Task 004 changes the walker constructor from no-arg to `__construct(SignatureCandidateEnumerator $enumerator)`. Existing tests at `ScopeWalkerTest.php`, `ScopeResolverTest.php`, `ScopedOrderByTest.php`, `ScopedOrderByFactoryTest.php`, and `ScopeContextTest.php` all do `new ScopeWalker()`. Task 004 lists *some* walker-test deletions/keepers but does NOT call out that **every** `new ScopeWalker()` construction site needs an enumerator argument.

**Applied fix**: Add to task 004's Context an explicit instruction: "Every `new ScopeWalker(...)` construction site in `packages/scope/tests/` must pass an enumerator. Use `new ScopeWalker(new SignatureCandidateEnumerator())` (default cap). Verified via `grep -rn 'new ScopeWalker(' packages/scope` returning only constructions that pass an enumerator." Tag the dependent test files explicitly.

### C6. Performance contract on the resolution path is asserted only in task 004; the SQL path is not equivalently locked down

Task 004 requires the test `'it does NOT iterate HasScopesInterface::overrides() on the resolution path'`. Good.

But the same contract on the SQL path — "the candidate list is bounded by cap × hash, not by stored overrides" — is implicit. The new SQL path (task 011 + 013) constructs a COALESCE chain from the enumerator output. If a worker accidentally iterates `$overrides->overrides()` somewhere in the renderer or in the query spec, the SQL chain length will be O(stored-overrides) and the performance contract silently breaks.

**Applied fix**: Add to task 013's Requirements: `'it does NOT call HasScopesInterface::overrides() at all during apply (the SQL path never reads stored override keys; it derives the chain from the enumerator)'`. Add to task 011's Requirements: `'it does NOT iterate stored overrides; the COALESCE chain length is exactly count(candidate signatures) + 1 for the fallback column'`.

### C7. `ScopeContext::state()` accessor signature must be specified to lock down the cache-key contract

Task 002 adds `ScopeContext::state(): array`. The enumerator's memoization key serializes this. If task 002 returns `array_keys($this->state)` (matching the existing `activeAxes()` return shape) instead of the full `array<string,string>` map, the cache key won't change when an axis path mutates from `eu.de` to `eu.fr` while the axis set stays the same — staleness bug.

The plan's Architecture Notes section already says `state(): array<string,string>` — but task 002's Requirements only say "exposes the active state map" and "empty array when no axes are active". A worker could implement it as `activeAxes()` and pass the tests.

**Applied fix**: Update task 002 Requirements to make this explicit: `'state() returns the full map of axis name to active path (not just keys); changing the path for an axis changes the returned map'`. Add: `'the enumerator cache returns a fresh result when the active path for an axis changes while the axis set stays the same'`.

### C8. `SignatureCandidateEnumerator` cache memoization granularity

Task 002 caches per `(serialize(attributeAxes), serialize(context.state()))`. Two issues:

1. `serialize()` on an array is fine but the plan should be explicit about the **order** of keys in the serialized representation. `['a' => 1, 'b' => 2]` and `['b' => 2, 'a' => 1]` serialize differently. Both `attributeAxes` (which is a `list<string>` — order matters for priority) and `context.state()` (which is an associative array — order may or may not matter depending on how `in()` was called) need normalization. State is order-of-insertion (PHP array semantics) — same logical state could produce different keys.
2. Cap is part of the enumerator's identity (constructor arg), not part of the cache key. If two enumerators share a cache (they don't because cache is per-instance), or if the cap is changed by a Plugin/Preference, cache values from before the change persist. Per-instance + readonly cap removes this risk; flag for awareness.

**Applied fix**: Update task 002 Requirements to add: `'context.state() returns axes ksorted before the cache key is computed (so the cache key is stable regardless of in() call order)'`. Make the canonical state representation explicit: `ksort($state)` before serializing.

Also add: `'the cache is per-enumerator-instance (changes to cap via a different enumerator instance produce a fresh result)'`.

### C9. Defensive-ignore on read claim is over-strong — explain the actual mechanism

The plan says: "Defensive ignore on read is implicit in the new algorithm: signatures stored with an axis outside the attribute will never appear in the candidate list, so they are never looked up. No runtime cost."

This is true for the **strict** case of "axis name not in `attributeAxes`". But the enumerator only enumerates signatures over axes in `attributeAxes`. A stored override like `"market:eu|locale:es"` for an attribute declared as `axes: ['locale']` would not be enumerated and would be silently ignored. Correct.

But what about a stored override with a path that doesn't exist in the registry (`"locale:xx-XX"` when `xx-XX` is not in the hierarchy)? The enumerator only walks up paths that ARE in the registry, so it never generates `"locale:xx-XX"` as a candidate either. Also correct.

Both cases of "stored garbage" are silently ignored. **However**, behavioral case 7 in the plan says "ignores stored signatures with axes not in the attribute axes (defensive ignore on read)". Task 004 lists this as a test. But the test as designed only proves "the value wasn't returned" — it doesn't prove "no validator was called" or "no extra work happened". The defensive-ignore guarantee is correctness, not performance.

**Applied fix**: Clarify in task 004's Requirements that case 7's test verifies correctness (the unknown-axis signature is not returned) and **separately** verify the performance contract via the `it does NOT iterate HasScopesInterface::overrides()` test already in the requirements. This is a doc fix in the task, not a code change.

### C10. `HasScopes` trait `$scopes` property is `?array` and `ksort`s every write — performance trap on bulk writes

`HasScopes::setOverride` does `ksort($scopes[$scopeKey])` and `ksort($scopes)` on **every write**. For an entity with N overrides, writing M new overrides is O((N+M) log (N+M) × M) — quadratic-ish over the storage. This is preserved behavior (task 007 is a parameter rename only), but it's worth flagging because the new composite signatures may produce many more keys per entity than the old single-axis-only model.

Not a blocker for this plan (preserved behavior), but worth a note. **Not auto-applied** — kept as a question for the team.

## Important (Should fix before building)

### I1. Task 002 mutates `ScopeHierarchy` from `readonly class` to non-readonly — verify trait/constructor implications

Task 002 says "switch to non-readonly class, but keep readonly on individual immutable constructor-promoted properties". This is fine in PHP 8.5, but the existing `ScopeHierarchy` has `private array $pathMap` and `private array $paths` declared **outside** the constructor signature and assigned in the body. Those properties cannot be `readonly` without a syntactic change (PHP only allows `readonly` on typed promoted properties, not on body-assigned ones). Worker may struggle.

**Applied fix**: Update task 002 to be specific: "Convert `ScopeHierarchy` from `readonly class` to a non-readonly `class`. The existing `$pathMap` and `$paths` properties become plain `private array` (not readonly). The new `$walkUpCache` field is `private array $walkUpCache = []`. Existing public API and constructor behavior MUST remain identical (no semantic change beyond memoization)."

### I2. Task 002's enumerator must NOT do per-context dynamic axis discovery — only use attribute axes

The plan's enumeration pseudocode iterates over `attributeAxes` (declared on the property), not over `context.activeAxes()`. This is correct: signatures are bounded by the attribute's declared axes, not by what's active in context. But task 002's "If an axis appears in `attributeAxes` but is NOT set in `$context`, that axis effectively becomes 'OMIT-only'" is correct in spirit but easy to miscode as "skip the axis entirely" → would emit incomplete signatures. The "OMIT-only" loop has exactly one iteration of OMIT, not zero iterations.

**Applied fix**: Add an explicit test to task 002: `'when an attribute axis is not set in context, that axis emits only OMIT (the loop contributes nothing to non-empty signatures)'`. Also add: `'when no attribute axes are set in context, the only signature emitted is empty and is therefore skipped, yielding an empty candidate list'`.

### I3. Task 004's "defensive ignore on read" test (case 7) needs a concrete fixture

Task 004 lists case 7 as a test but doesn't pin down the fixture. With ambiguity, a worker may write a test that passes trivially. Define the fixture: attribute declared with `axes: ['locale']`, storage contains `"market:eu"` (unknown axis), context has `locale: es`. The walker MUST return `notFound` (or fall through to the entity column), and crucially MUST NOT call `$overrides->override('market:eu', ...)`.

**Applied fix**: Add concrete fixture to task 004's case-7 requirement.

### I4. `ScopeWalker::walk()` no longer takes `$registry` per task 004 — but interface keeps it

Task 004 says "The walker no longer takes `$registry` directly to compute walk-ups — the enumerator does that. Keep the parameter on the signature for now". Keeping unused parameters violates code-standards principle (CLAUDE.md "Explicit over implicit"). PHPStan level 8 will flag unused-but-required params on a non-interface method.

Also, the existing `ScopeResolver::resolved()` calls `walk(...registry: $this->scopeContext->registry())`. If the parameter is dropped from `walk()`, the resolver needs updating in the same task. If it's kept, PHPStan / phpcs may complain about an unused argument.

**Applied fix**: Task 004 removes the `$registry` and `$context` arguments from `walk()` cleanly. The enumerator already has the registry (injected) and the context (passed). New `walk()` signature: `walk(HasScopesInterface $overrides, string $property, array $axes, ScopeContext $context): ScopeWalkResult`. `ScopeResolver::resolved()` updates accordingly to drop the registry argument. (The enumerator needs a way to get the registry — it can take it via context (`$context->registry()`) or via constructor. Constructor injection is cleaner.)

Update task 002 enumerator signature: `enumerate(array $attributeAxes, ScopeContext $context): array<ScopeSignature>` — the cap is the enumerator's own state, the registry comes from `$context->registry()`.

Update task 004's Context to reflect the simpler walk() signature. Update task 005's walkAt signature to match style (it still needs registry to call walkUp; pass it via the enumerator or get it via context).

### I5. Task 005's `walkAt` no longer matches the enumerator pattern — flag the asymmetry

Task 005 keeps walkAt's old code path (direct walkUp + array_find), not using the enumerator. Fine — walkAt is single-axis by spec, so enumeration is trivial. But the registry parameter needs cleanup: today walkAt takes `$registry` as a positional argument; if walk() loses its registry param (per I4), walkAt's stays — asymmetric. Either both lose it (use `$context->registry()`) or both keep it. Pick one.

**Applied fix**: Update task 005 so `walkAt(HasScopesInterface $overrides, string $property, array $axes, ScopeSignature $signature, ScopeRegistryInterface $registry): ScopeWalkResult` — explicit registry stays (walkAt doesn't take a context at all, by spec it ignores context). This is the existing shape and is fine.

### I6. Task 010 `ScopedFieldExpression` carrying `candidateSignatures` is duplicate state with the enumerator's output — clarify ownership

Task 010 says `ScopedFieldExpression` carries `list<ScopeSignature> $candidateSignatures`. Who computes these? Per task 013, `ScopedOrderBy::apply()` calls `enumerator.enumerate(...)`, then constructs a `ScopedFieldExpression`, then passes it to the renderer. Clear ownership: the query spec is the bridge. Good.

But: `ScopedFieldExpression` is described as `readonly class`. If `candidateSignatures` is `array` typed, PHPStan level 8 will want a generic shape comment, and the worker needs to know the contract is "in descending-score order — the renderer does not re-sort". Currently the task says "in descending-score order" in the docblock but does not list this as a tested guarantee.

**Applied fix**: Add to task 010 Requirements: `'ScopedFieldExpression preserves the candidateSignatures order as given (no internal sort or normalization in the constructor — the order is the renderer's contract)'`. Add to task 011 Requirements: `'PgSqlScopedFieldRenderer emits COALESCE branches in the exact order of the expression's candidateSignatures (no re-sorting)'`.

### I7. Task 011's identifier validation pattern for composite signatures

Task 011 says "For each signature, split each axis name and each path segment (the dot-separated parts of the value) and validate every one." OK. But composite signatures stored as the JSONB key (e.g. `channel:b2b|locale:es.es`) contain the `|` and `:` separators — the renderer must construct that key and inject it into the SQL string. If the validation pass is per-axis, the constructed key is safe. But the task doesn't explicitly say "validate before concatenating into the JSON key string".

**Applied fix**: Add to task 011 Requirements: `'it constructs each JSONB key by alphabetically sorting axes and joining with | and : separators ONLY after every axis name and every path segment has been validated as a safe identifier'`. And: `'it throws InvalidColumnException when a composite signature contains an axis name that fails IdentifierValidator'`.

### I8. Cap warning emission must be once-per-call, not once-per-process

Task 002 says "fire a warning ... ONCE per call, then truncate". Task 013 has `'it emits the cap-exceeded warning once when the candidate count exceeds the configured cap'`. PHP's `trigger_error` fires every time it's called — if the enumerator is called repeatedly (e.g. for many properties on many entities), the warning will spam. The plan says "ONCE per call" — make sure the implementation actually means "once per invocation of `enumerate()`", not "once per process". The cap is exceeded inside a single `enumerate()` call, so one warning per overflowing call is correct.

But repeated calls with the same overflowing axes will each emit a warning. For real-world apps with high traffic, this is log noise. Acceptable for v0; flag as a potential follow-up.

**Not auto-applied** — kept in the Questions list.

### I9. Task 014 transactional-rollback test pattern with Postgres

Task 014 says "All tests run inside a transaction that rolls back at the end". But the same task also says tests run the schema diff to create the table at the start, and DROP the table at the end. DDL (CREATE TABLE, CREATE INDEX) in Postgres is transactional, but mixing transactional cleanup with non-transactional fixtures is fragile (e.g. if the test crashes after CREATE TABLE but before BEGIN, the table leaks; if the test BEGINs then runs CREATE TABLE inside the transaction, ROLLBACK undoes the table creation).

Cleaner pattern: do the CREATE outside transactions in `beforeEach`; do the data writes inside a transaction; ROLLBACK to undo data writes; DROP TABLE in `afterEach` (idempotent with `DROP TABLE IF EXISTS`).

**Applied fix**: Update task 014's Context to specify the cleanup pattern: "Schema CREATE and DROP statements run outside transactions in `beforeEach` / `afterEach`. Per-test data writes run inside a transaction that the test ROLLBACK at the end (or commits — `DROP TABLE` in afterEach cleans up either way). Use `DROP TABLE IF EXISTS \"<table>\"` in afterEach for crash resilience."

### I10. Task 014 uses unique table names per test run — but the auto-emitted GIN index name is `<table>_scopes_gin` — verify collision-free

Plan says index name is `<table>_scopes_gin`. Postgres requires unique index names within a schema. With unique table names per test (`scope_int_test_<random>`), the index names are also unique. Good. But concurrent test runs in parallel pest workers MAY collide if random table names collide. Plan should specify the randomization source (e.g. `bin2hex(random_bytes(8))`) to make collisions astronomically unlikely.

**Applied fix**: Update task 014's Context: "Use `'scope_int_' . bin2hex(random_bytes(8))` for unique table names per test."

### I11. Module registration test expectations need updating

`packages/scope-pgsql/tests/Unit/ModuleTest.php` currently asserts `ScopeSortRendererInterface` is bound to `PgSqlScopeSortRenderer`. Task 011 says to update this test. Good.

`packages/scope/tests/Unit/ModulePhpTest.php` asserts `ScopeSortRendererInterface` is NOT bound and uses `NoDriverException::noDriverInstalled()` whose message mentions "scope sort renderer". After the rename to `ScopedFieldRendererInterface`, the exception message AND the test both need updating. Task 010 doesn't call this out.

**Applied fix**: Update task 010's Acceptance Criteria to include: "Updates `packages/scope/tests/Unit/ModulePhpTest.php` to use `ScopedFieldRendererInterface` instead of `ScopeSortRendererInterface`. Updates `NoDriverException::noDriverInstalled()` message/suggestion to reference scoped field renderer (the new vocabulary) and to keep the `markommerce/scope-pgsql` suggestion."

### I12. ScopedOrderByFactoryTest needs updating — task 013 covers ScopedOrderBy rewrite but not the factory test

Task 013 says "`ScopedOrderByFactory` (unchanged signature, internals updated to inject the enumerator)". The existing factory test `ScopedOrderByFactoryTest.php` passes `ScopeSortRendererInterface` to the factory constructor (and uses `makeFactoryRenderer()` to fake it). After task 013, the constructor takes `ScopedFieldRendererInterface` and an enumerator. The test needs updating to construct the new factory signature.

**Applied fix**: Add to task 013's Context: "Update `packages/scope/tests/Unit/Query/ScopedOrderByFactoryTest.php` and `ScopedOrderByTest.php`: the factory and the spec now take `SignatureCandidateEnumerator` and `ScopedFieldRendererInterface` instead of `ScopeSortRendererInterface`. Update `makeFactoryRenderer()` and `makeRenderer()` helpers to construct fakes of the new interface."

### I13. `ScopeSignature::fromString` must reject duplicate-axis input — task 001 says so but verify the parse semantics

Task 001 requirement `'it throws InvalidSignatureException when fromString receives a signature with duplicate axes'` — good. But the parser needs to scan all axes BEFORE building the value to detect this. Specifically: `fromString('locale:es|locale:de')` — two `locale:` entries — must throw. The signature class's canonical form is "alphabetically sorted, no duplicates"; the parser is the choke point.

**Applied fix**: Add an explicit example to task 001 Requirements: `'fromString throws when given "locale:es|locale:de" (duplicate axis names)'`. Already implied; making it concrete prevents the worker from missing it.

### I14. Empty path segment validation for `ScopeSignature`

Task 001 requires throwing on empty axis name or empty value. Good. But what about `:` literal in an axis name or path? `axis:value` separator is fixed; if a path contains `:`, parsing breaks. Real-world paths are dot-separated (e.g. `eu.de.berlin`); colons in paths are not expected. But the parser must handle it deterministically.

**Applied fix**: Add to task 001 Requirements: `'it throws InvalidSignatureException when fromString receives a part containing more than one colon (axis:value:extra)'`. (The renderer's IdentifierValidator already rejects non-identifier chars; this catches malformed strings earlier.)

### I15. Documentation tasks 009 and 015 reference deprecated APIs

Task 009 README test requirements use the exact regex `'the docs page lists ScopeSignature in the API Reference table'`. This is fine, but the existing `docs/src/content/docs/packages/scope.md` page already mentions `Scope` extensively. The task says to update it, but the test as a regex is a presence check; it doesn't catch lingering references to `Scope`. Add a negative check.

Already covered by task 006's `'no documentation file under docs imports Markommerce\Scope\Scope in a code block'` — good. But that's a code-block check, not a prose check. Prose mentioning the old class can drift. **Not blocking.**

**Not auto-applied** — defer.

### I16. Task 008 changes resolver constructor — module.php and DI need updates

Task 008 adds `ScopeSignatureValidator` to `ScopeResolver`'s constructor. `module.php` already lists `ScopeResolver::class` as a singleton but doesn't manually construct it — DI handles auto-wiring. As long as `ScopeSignatureValidator` is also a singleton (which task 008 specifies), auto-wiring works.

But: `ScopeSignatureValidator` takes `ScopeRegistryInterface` (per task 003), which is bound via factory. Auto-wiring should resolve this if the container supports constructor injection of factory-bound interfaces. Verify the Marko container supports this (typically yes for `ContainerInterface::get(I::class)` style autowiring).

**Not auto-applied** — flag for awareness; tests in task 008 will catch any issue.

## Minor (Nice to address)

### M1. `ScopeContext` mutation on cache key requires re-serialization on every call

The plan's "Out of cache" section says comparing the serialized active state on each call is `O(active-axes)`. Each comparison is a `serialize()` call + string comparison — for 5 axes, microseconds. Fine. But documenting that "the cache provides a benefit only when the same enumerate() call is made many times *between* context mutations" is honest.

### M2. ScopeWalker's `walk()` deprecates the registry parameter — old callers will break compile

If task 004 keeps the registry parameter for binary compatibility, every old caller still works. If it drops it (per I4), every old caller breaks. Just be deliberate. Plan should choose.

### M3. PgSqlScopedFieldRenderer: COALESCE with no candidates → bare column reference

Task 011 says "Empty signatures list → emit `<column>` alone (no COALESCE)". Good. But the column name MUST still be validated. The task implies this via "validate ... `$expression->column`". Confirm by test.

### M4. CHANGELOG message about "pre-existing single-axis overrides will continue to resolve via the new walker"

Task 009's CHANGELOG draft says "pre-existing single-axis overrides will continue to resolve via the new walker". Verify this is actually true: the new walker generates `"locale:es"` as a candidate string (alphabetically-single-axis → joined → `"locale:es"`), which matches the old format. Yes, this is true. CHANGELOG message is accurate.

### M5. `composer test` exclude-group

`composer.json` already has `--exclude-group=integration-destructive`. No code change needed for task 014's group-tag to take effect at the runner level. Good.

## Questions for the Team

### Q1. Should the cap warning be once-per-process or once-per-call?

Currently planned: once per `enumerate()` call. For a request that resolves N scoped properties, this is N warnings. Logger noise risk. Consider once-per-process via a static guard, or wire to PSR-3 with a "warning fatigue" pattern. Pick one; document.

### Q2. Should `HasScopes` trait stop ksort-ing on every write?

`setOverride` ksorts the whole storage on every write. For large storage with many writes (bulk import), this is O(N log N × M). Could be deferred (sort only when serialized for storage) or removed (storage shape order doesn't matter for resolution since reads are direct key lookups). Behavior preserved by task 007 (parameter rename only). Worth a future-plan note.

### Q3. Should `ScopedSelect` / `ScopedWhere` be a separate follow-up plan?

Per C1, they're being dropped from this plan because `selectRaw` / `whereRaw` don't exist. Should we file a follow-up plan now to (a) add raw select/where to `marko/database`, then (b) add the scoped versions? Or is multi-position scoped resolution a "nice to have" deferred indefinitely?

### Q4. GIN index name uniqueness across schemas

Postgres index names are scoped to schemas, not databases. Tests use the default `public` schema. Real apps may use multiple schemas. Index name `<table>_scopes_gin` is fine within a schema, but if the same table name appears in two schemas, naming collision is not a concern. Document in the README.

### Q5. Cap default of 256

Brief picks 256. For an attribute with 3 axes each having 5 hierarchy levels, the cartesian product is 5^3 = 125 — well under 256. For 4 axes × 5 levels = 625 — exceeds. Is 256 too low? Or is "if your attribute has 4 axes × 5 levels you should reconsider your model" the right default position? Document the math, let users tune.
