# Plan: Scope Composite Overrides (Multi-Axis, Lexicographic Scoring)

## Created
2026-05-19

## Status
completed

## Objective
Refactor `markommerce/scope` and `markommerce/scope-pgsql` to replace the current single-axis-only "first axis wins" resolution with multi-axis composite overrides scored lexicographically by the attribute's declared axis priority order. JSONB-native, Postgres 16+ only, performance-first, breaking change accepted (pre-release).

## Related Issues
none

## Discovery Notes

### Current state read from source

- `ScopeWalker::walk()` iterates an attribute's declared axes; for each axis it walks up the hierarchy and returns the first match within that axis. Cross-axis fallthrough goes axis-by-axis. This is the "first axis wins" model the refactor replaces.
- `ScopeWalker::walkAt(Scope $scope)` resolves a single axis explicitly, ignoring `ScopeContext`. Already takes a single-axis `Scope`.
- `HasScopesInterface` stores under flat string keys (e.g. `"geo:eu.de"`), which equal `Scope::toString()`. Storage shape: `{"axis:path": {"property": value}}`. Trait `HasScopes` ksorts both dimensions on write — preserves explicit `null` overrides ("found null != not found").
- `ScopeResolver::setOverride(Entity, prop, value, Scope)` writes `Scope::toString()` to storage. No validation that the scope's axis appears in the attribute's `#[Scoped(axes:[…])]` or that the path exists in the registry (write side trusts the caller).
- `ScopedOrderBy` + `ScopeSortExpression` + `ScopeSortRendererInterface` already emit a COALESCE chain. Today the chain is "per-axis walk-up paths concatenated in declared axis priority order" — close in shape but semantically different from the new "cartesian-product of composite signatures in descending score order".
- `scope-pgsql` ships only the sort renderer + an auto-migration that adds a bare JSONB column via `marko/database`'s `SchemaRegistry` (no index today, no migration helper).
- The test base is Pest 4. No test currently hits real Postgres. `integration-destructive` is defined as the project convention (CLAUDE.md, composer.json) but is not used by any test yet. Docker Compose provides a real Postgres at `postgres:5432/playground` (env vars: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
- `ScopeContext::activeAxes()` and `::registry()` exist and are used. `ScopeContext::clearAll()` is the request-boundary reset hook.
- `Marko\Database\Schema\Index` exists and supports `IndexType::INDEX` — used to declare GIN indexes via the auto-migration path. `PgSqlGenerator` is the Postgres-specific schema generator from `marko/database-pgsql`.
- `ScopeMetadataFactory` is per-class memoized; safe to depend on.

### Assumptions resolved during clarification

1. **Single API for scopes**: drop the `Scope` value object entirely; `ScopeSignature` is canonical. A single-axis signature is a composite-with-one-axis. `walkAt(ScopeSignature)` rejects `count(axes) !== 1` with a clear error. Breaking change accepted.
2. **Migration helper**: hook into Marko's auto-migration. Any entity whose schema includes the `scopes` JSONB column automatically gets a `jsonb_path_ops` GIN index emitted at schema-build time. Zero-config for users.
3. **Real-Postgres integration tests**: tag `integration-destructive`, excluded from `composer test`, included in `composer test:all`. Connect via `Marko\Database\PgSql\Connection\PgSqlConnection` using the DB_* env vars already provided by compose.
4. **Candidate cap default**: 256, configurable via the enumerator's constructor argument. Exceeding it emits a warning (PSR-3 logger if available, else `trigger_error(E_USER_WARNING)`) and truncates the candidate list. Documented in README.
5. **Defensive ignore on read** is implicit in the new algorithm: the walker generates candidate signatures from (attribute axes + context) and looks them up by string key. Signatures stored with an axis outside the attribute will never appear in the candidate list, so they are never looked up. No runtime cost.
6. **Storage parameter renamed**: `HasScopesInterface` `$scopeKey` → `$signature` for clarity. Breaking parameter rename.
7. **Sort renderer generalized**: `ScopeSortRendererInterface` → `ScopedFieldRendererInterface`, `ScopeSortExpression` → `ScopedFieldExpression`. The renderer emits a generic SQL expression (no `ORDER BY`/direction baked in). Reused by `ScopedOrderBy`, `ScopedSelect`, `ScopedWhere`.

### Performance principles (non-negotiable)

- **Resolution path = O(cap × hash-lookup)**, not O(stored-overrides). The walker generates a bounded candidate list in descending-score order; storage is hash-lookup. First hit wins.
- `ScopeHierarchy::walkUp()` results are memoized per (axis, path).
- `SignatureCandidateEnumerator` results are memoized per (attribute-axes-tuple, context-snapshot, cap). Cache resets on `ScopeContext::clearAll()` and on any mutation of the context's active state.
- `ScopeSignature::toString()` precomputed once (the class is readonly; deterministic from axes).
- The COALESCE chain in scope-pgsql is built from the same enumerator output. PHP and SQL paths share one source of truth — no algorithmic drift.
- No string re-parsing on the read hot path.
- Validator caches `(signature, attribute-axes-tuple)` validations.

## Scope

### In Scope
- `ScopeSignature` value object: immutable, alphabetically-sorted axes, deterministic `toString` / round-trip `fromString`, equality.
- `SignatureCandidateEnumerator`: generates candidate signatures in descending-score (lexicographic by declared axis priority) order, with configurable cap + warning on overflow + per-request memoization.
- `ScopeScorer` (optional — may be subsumed by the enumerator since enumeration order *is* the score). If kept, used by the walker for tiebreak debug; if not, the enumerator's iteration order suffices.
- `ScopeSignatureValidator`: rejects signatures with axes not in the attribute's declared axes; rejects values not in the registry's hierarchy. Cached.
- `ScopeWalker` rewrite: `walk()` uses the enumerator + first-hit-wins direct lookup; `walkAt(ScopeSignature)` accepts only single-axis signatures.
- `HasScopesInterface` parameter rename (`$scopeKey` → `$signature`); `HasScopes` trait updated.
- `ScopeResolver` `setOverride` / `clearOverride` take `ScopeSignature` and validate before writing.
- `Scope` value object deleted; all callers updated (resolver, tests, walker).
- `ScopeHierarchy::walkUp()` memoization.
- `ScopedFieldRendererInterface` + `ScopedFieldExpression` (replacing the `ScopeSort*` types).
- `PgSqlScopedFieldRenderer` (replacing `PgSqlScopeSortRenderer`): emits the COALESCE chain from a list of candidate signatures.
- `ScopedOrderBy` (rewritten), using the same enumerator + field renderer. **NOTE**: `ScopedSelect` and `ScopedWhere` are explicitly OUT OF SCOPE for this plan — they require `selectRaw`/`whereRaw` on `Marko\Database\Query\QueryBuilderInterface`, which do not exist today. Adding them is a marko-database change deferred to a follow-up plan.
- Auto-migration adds a `jsonb_path_ops` GIN index on the `scopes` column at migration-apply time. Implemented as a side-channel in `markommerce/scope-pgsql` that emits a raw `CREATE INDEX IF NOT EXISTS "<table>_scopes_gin" ON "<table>" USING GIN ("scopes" jsonb_path_ops)` per `HasScopes`-bearing table, executed alongside the normal schema-apply. Idempotency is from the SQL `IF NOT EXISTS`, not from the diff calculator (Marko's schema layer has no GIN type today; adding it upstream is out of scope).
- Real-Postgres integration tests tagged `integration-destructive`: round-trip composite write/read, COALESCE chain correctness, GIN index existence, transactional rollback.
- READMEs (both packages) updated with single-axis, two-axis composite, three-axis composite, and `walkAt` examples.
- `CHANGELOG.md` in both packages describing the breaking change.

### Out of Scope
- Primary-context functional indexes (deferred until benchmarking).
- Dynamic / ad-hoc indexing.
- Materialized views, partitioning.
- Custom merchant-defined attributes.
- MySQL adapter.
- Data migration from old single-axis storage to new composite storage. Pre-release; breaking change is acceptable.
- Performance benchmarking infrastructure beyond correctness verification.
- `ScopedSelect` and `ScopedWhere` query specifications — they require `selectRaw`/`whereRaw` methods on `Marko\Database\Query\QueryBuilderInterface` which do not exist today. Adding them is a marko-database change deferred to a follow-up plan. `ScopedOrderBy` (which uses the already-existing `orderByRaw`) is in scope.
- Adding GIN / operator-class support to Marko's schema types (`IndexType` enum, `Schema\Index`, `PgSqlGenerator`, `PgSqlIntrospector`). The auto-emitted scopes GIN index is emitted via a side-channel raw-SQL statement inside `scope-pgsql`, not via the schema-diff pipeline.
- Anything outside the `packages/scope/` and `packages/scope-pgsql/` directories except the two READMEs and CHANGELOGs (and the root `docs/src/content/docs/packages/scope*.md` pages updated to match).

## Success Criteria
- [ ] All 19 behavioral test cases in the task brief pass as Pest tests (cases 1–19; equal-score determinism case 19 is defensive).
- [ ] Real-Postgres integration tests pass under `composer test:all` (round-trip, COALESCE chain order in ORDER BY, GIN index presence + IF-NOT-EXISTS idempotency, transactional rollback, cap-warning emission).
- [ ] `composer test` (default, parallel, `integration-destructive` excluded) is fully green.
- [ ] `composer test:all` is fully green (assumes the compose Postgres is up).
- [ ] `./vendor/bin/phpcs` is clean across both packages.
- [ ] `./vendor/bin/php-cs-fixer fix --dry-run` is clean.
- [ ] `./vendor/bin/phpstan analyse` (level 8) is clean across both packages.
- [ ] The old single-axis-only `Scope` class is gone (file deleted; no `use Markommerce\Scope\Scope;` remains anywhere in `packages/scope*/**` or `docs/**`).
- [ ] The old `ScopeSortRendererInterface` / `ScopeSortExpression` / `PgSqlScopeSortRenderer` types are gone (replaced by `ScopedField*`).
- [ ] `ScopeWalker` no longer iterates `HasScopesInterface::overrides()` on the resolution hot path (it uses direct hash lookups instead).
- [ ] `PgSqlScopedFieldRenderer` does not iterate `HasScopesInterface::overrides()` either — the COALESCE chain length equals `count(candidate signatures) + 1`.
- [ ] `ScopedSelect` / `ScopedWhere` are NOT introduced (deferred — requires `selectRaw`/`whereRaw` on marko/database, not yet present).
- [ ] No changes to Marko's `IndexType`, `Schema\Index`, `Attributes\Index`, `PgSqlGenerator`, or `PgSqlIntrospector`.
- [ ] READMEs in both packages and the matching docs pages cover single-axis, two-axis composite, three-axis composite, `walkAt`, and the candidate-cap behavior.
- [ ] CHANGELOG entries in both packages describe the breaking change with a migration note ("packages are pre-release; pre-existing overrides will not be read by the new walker — drop the `scopes` column and rewrite").

## Task Overview

| Task | Description | Depends on | Status |
|------|-------------|------------|--------|
| 001 | `ScopeSignature` value object (immutable, alphabetical, round-trip parse/serialize, equality, hasAxis/get accessors) | — | completed |
| 002 | `SignatureCandidateEnumerator` (descending-score order, cap + warning, per-context memoization) **and** `ScopeHierarchy::walkUp` memoization | 001 | completed |
| 003 | `ScopeSignatureValidator` (axes-in-attribute, values-in-hierarchy, cached) + `InvalidSignatureException` | 001 | completed |
| 004 | `ScopeWalker::walk()` rewrite — enumerator + first-hit-wins direct lookup; covers all of behavioral cases 1–13 and the preserved null-as-found semantics | 001, 002 | completed |
| 005 | `ScopeWalker::walkAt(ScopeSignature)` accepts only single-axis signatures (cases 14, 15) | 004 | completed |
| 006 | Delete `Scope` value object; update all references in `packages/scope` (resolver signatures, tests, docs imports) | 004, 005 | completed |
| 007 | `HasScopesInterface` + `HasScopes` trait rename `$scopeKey` → `$signature` (cosmetic, breaking parameter name) | — | completed |
| 008 | `ScopeResolver::setOverride/clearOverride(ScopeSignature)` — validate via `ScopeSignatureValidator` before writing (cases 16, 17) | 003, 006, 007 | completed |
| 009 | `markommerce/scope` README + CHANGELOG + `docs/src/content/docs/packages/scope.md` updated for composite API | 008 | completed |
| 010 | `ScopedFieldExpression` + `ScopedFieldRendererInterface` (generic SQL expression; replaces `ScopeSortExpression` / `ScopeSortRendererInterface`) | 001 | completed |
| 011 | `PgSqlScopedFieldRenderer` — emit COALESCE chain from a list of candidate signatures + fallback column; replaces `PgSqlScopeSortRenderer` | 010 | completed |
| 012 | Auto-migration: side-channel emitter in `scope-pgsql` that produces a raw `CREATE INDEX IF NOT EXISTS "<table>_scopes_gin" ON "<table>" USING GIN ("scopes" jsonb_path_ops)` per HasScopes-bearing table (no schema-diff participation; Marko's IndexType has no GIN) | — | completed |
| 013 | `ScopedOrderBy` rewritten — backed by the shared enumerator + field renderer (cases: COALESCE chain order, generated candidate list matches expected, cap warning emission test). `ScopedSelect`/`ScopedWhere` are out of scope (require `selectRaw`/`whereRaw` not present in marko/database) | 002, 010, 011 | completed |
| 014 | Real-Postgres integration tests (`integration-destructive`) — round-trip composite write/read, COALESCE chain in ORDER BY, GIN index introspection, IF-NOT-EXISTS idempotency, set+remove transactional rollback (uses a PgSqlConnection subclass overriding createPdo to bypass DatabaseConfig) | 008, 012, 013 | completed |
| 015 | `markommerce/scope-pgsql` README + CHANGELOG + `docs/src/content/docs/packages/scope-pgsql.md` updated for composite API and integration-test instructions | 014 | completed |

## Architecture Notes

### Resolution algorithm (PHP)

```
ScopeWalker::walk(overrides, property, attributeAxes, context):
    candidates = enumerator.enumerate(attributeAxes, context)
        // candidates are ScopeSignature objects in descending-score order
        // (registry is injected into the enumerator; cap is on the enumerator)
    foreach candidates as sig:
        sigString = sig.toString()
        if overrides.hasOverride(sigString, property):
            return found(overrides.override(sigString, property))
    return notFound()
```

Note: the rewritten `walk()` drops the `$registry` parameter — the enumerator owns the registry (injected at construction time) and the context is passed through. `walkAt()` retains its `$registry` parameter because it is single-axis, context-free, and does not use the enumerator.

`hasOverride` uses `array_key_exists` (not `isset`) → preserves explicit `null` overrides as "found".

### Enumeration order

For attribute axes `[A, B, C]` declared in priority order (earlier = higher priority), with context values that produce walk-ups `walkA = [a3, a2, a1]`, `walkB = [b2, b1]`, `walkC = [c4, c3, c2, c1]`:

```
for vA in (a3, a2, a1, OMIT):
    for vB in (b2, b1, OMIT):
        for vC in (c4, c3, c2, c1, OMIT):
            if (vA, vB, vC) == (OMIT, OMIT, OMIT): continue  // empty signature
            emit ScopeSignature(non-omitted axes)
```

This iteration is exactly lexicographically descending on the score tuple `(depthA, depthB, depthC)` where `depth = count(walk) - index` and `depth(OMIT) = 0`. Verified against behavioral cases 3, 4, 5, 10, 11.

Cap: enumeration stops when `cap` signatures are emitted; warning is fired once per resolution.

### Signature canonical form

Signatures sort their axes alphabetically before joining with `|`. Example: input `{locale: es, channel: b2b}` → `"channel:b2b|locale:es"`. Same logical signature always produces the same string. `fromString(toString(sig)) == sig` always.

### Storage shape (unchanged on disk, new semantics)

JSONB column `scopes` shape: `{"<sigString>": {"<property>": <value>}}` where `<sigString>` is now any alphabetical-axis signature, not just `"axis:path"`. Single-axis signatures are unchanged shape (`"locale:es"`), composite adds new key forms (`"channel:b2b|locale:es"`).

### `jsonb_path_ops` GIN index

```sql
CREATE INDEX IF NOT EXISTS "<table>_scopes_gin" ON "<table>" USING GIN ("scopes" jsonb_path_ops)
```

`jsonb_path_ops` is smaller and faster than the default operator class for the access patterns we use (`->` and `->>` key lookups). Added automatically at migration-apply time for any table with a `scopes` JSONB column.

**Implementation note**: Marko's `IndexType` enum only supports `Btree`, `Unique`, `Fulltext`. There is no GIN support in `Schema\Index`, `PgSqlGenerator`, or `PgSqlIntrospector`. Adding GIN to the schema layer is out of scope for this plan. Instead, `markommerce/scope-pgsql` emits the index via a side-channel: a separate raw-SQL statement (`CREATE INDEX IF NOT EXISTS ...`) executed alongside the normal schema apply for any table whose entity (or extender) uses the `HasScopes` trait. The `IF NOT EXISTS` clause provides idempotency without requiring participation in `DiffCalculator`.

### `ScopedFieldRendererInterface` shape

```php
interface ScopedFieldRendererInterface
{
    /**
     * Emit a SQL expression that resolves <property> for a row, given a list of
     * candidate signatures in descending-score order and a fallback column.
     *
     * @throws InvalidColumnException
     */
    public function render(ScopedFieldExpression $expression): string;
}
```

Output is a bare SQL expression (no `ORDER BY`, no `AS`, no `=`) — the calling query specification wraps it with `orderByRaw` (the only raw method currently exposed by `Marko\Database\Query\QueryBuilderInterface`). `selectRaw` and `whereRaw` do not exist on that interface today, which is why `ScopedSelect` / `ScopedWhere` are out of scope for this plan.

### Test grouping for real Postgres

Tagged via Pest's `->group('integration-destructive')`. Excluded by `composer test`'s `--exclude-group=integration-destructive`. Included in `composer test:all`. Tests assert against the compose-stack Postgres via `Marko\Database\PgSql\Connection\PgSqlConnection`. If the connection cannot be established, the test must skip with a clear message (do not fail) — running the destructive suite without Postgres is a known limitation, not a bug.

### Out of cache / out of band

Per-context memoization in `SignatureCandidateEnumerator` uses a string cache key derived from `(serialize($attributeAxes), serialize(ksort($context->state())))`:
1. `$attributeAxes` is a `list<string>` — order matters (it's the declared priority).
2. `$context->state()` returns `array<string, string>` (axis-name → active-path). `ksort()` it before serializing so the cache key is stable regardless of the order in which `ScopeContext::in()` was called.

The enumerator observes `ScopeContext` mutations by comparing the cache key on each call (`O(active-axes)`). Avoids needing observer machinery.

The cache is per-enumerator-instance. The cap is a constructor argument and so is implicitly part of the cache identity (a different enumerator instance with a different cap has its own cache).

## Risks & Mitigations

- **Risk**: enumeration order subtly miscomputes score → wrong winner. **Mitigation**: behavioral cases 3, 4, 5, 10, 11 are written first as Pest tests and the walker must pass all five before any other code is written (task 004 is strict TDD).
- **Risk**: cap of 256 is silently exceeded in real apps with 4+ axes → resolution stops returning expected values. **Mitigation**: warning is loud (PSR-3 if bound, `trigger_error(E_USER_WARNING)` otherwise) AND tested. README documents the cap, the warning, and how to raise it.
- **Risk**: composite write API regression — tests that wrote `Scope` no longer compile after deletion. **Mitigation**: task 006 explicitly updates every test file in `packages/scope/tests/` and `packages/scope-pgsql/tests/` that imports `Markommerce\Scope\Scope`; task 008 holds the resolver migration.
- **Risk**: GIN index migration breaks existing apps (re-runs on tables that already have an index). **Mitigation**: the side-channel uses `CREATE INDEX IF NOT EXISTS` so re-running is a no-op at the Postgres level — no participation in `DiffCalculator` is needed (and Marko's schema layer cannot represent GIN today anyway). Test the no-op path with a real Postgres in task 014 by running the apply twice.
- **Risk**: real-Postgres integration tests require docker compose Postgres to be up. **Mitigation**: tests `skipIf` the connection cannot be opened; documented in CHANGELOG + README. `composer test` does not run them so the default loop is unaffected.
- **Risk**: per-context enumerator memoization gets stale across requests in long-running PHP processes (FPM workers). **Mitigation**: cache key includes the serialized active state of `ScopeContext`. `clearAll()` between requests changes the key automatically. Documented in `ScopeContext` docblock (already says "MUST call `clearAll()` between requests").
- **Risk**: `ScopeHierarchy::walkUp` memoization races in shared-memory parallel execution. **Mitigation**: each PHP process has its own copy of the hierarchy (it's constructed per request from `PhpScopeRegistry`); no cross-process sharing. PHP-FPM workers are single-threaded.
- **Risk**: docs page in `docs/src/content/docs/packages/scope.md` drifts from the new API. **Mitigation**: task 009 updates it in lockstep with the README; the post-implementation doc-updater pipeline checks it again at end.
- **Risk**: renaming `ScopeSort*` → `ScopedField*` breaks downstream code that bound the old interface. **Mitigation**: no downstream code exists yet (scope-pgsql is the only known binder, and it's in scope of this plan); CHANGELOG calls it out.
- **Risk**: enumerator's cache key (`serialize(state)`) is slow for very large contexts. **Mitigation**: contexts in practice have ≤5 active axes; serialize is microseconds. Benchmark only if it becomes a hot spot.
