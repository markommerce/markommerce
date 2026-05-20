# Plan: Scope Axis Configuration

## Created
2026-05-20

## Status
completed

## Objective
Let the framework, packages, and merchants each contribute scope axes through `config/scope.php` files that deep-merge. Ship `locale`, `market`, and `channel` axes by default, define a per-axis "default scope" whose semantics are "equivalent to the base column", and short-circuit SQL queries to plain column reads when every active axis is at its default.

## Related Issues
none

## Discovery Notes

Established during brainstorm and grounded by reading the current package:

- `packages/scope` has no `config/` directory today; merchants must define `scope.axes` themselves. This plan adds the package-shipped default file.
- The current axis schema is `'axes' => ['name' => ['hierarchy' => [list of dotted paths]]]`. Marko's `ConfigMerger` (`marko/packages/config/src/ConfigMerger.php:18-28`) deep-merges associative arrays and *replaces* indexed arrays. The indexed `hierarchy` list therefore clobbers across sources. The fix is to make per-axis scopes an **associative map** keyed by scope path so multiple packages can extend a shared axis additively.
- Each axis gains a required `default` config key naming its root scope. **Being at the default scope is defined to mean "equivalent to the base column" / "not scoped on that axis"**. The default scope is never stored in the `scopes` JSON column; writing an override at a default-scope signature is a loud error.
- The optimization mechanism: `SignatureCandidateEnumerator::enumerate()` filters the axis default out of `walkUp()` results before building the cartesian product. When every axis collapses to OMIT-only, the candidate list is empty, and the existing short-circuits in `ScopedOrderBy::apply()` (`packages/scope/src/Query/ScopedOrderBy.php:45-49`) and `PgSqlScopedFieldRenderer::render()` (`packages/scope-pgsql/src/Query/PgSqlScopedFieldRenderer.php:22-24`) emit a plain column with no `scopes` JSON access.
- `scope-pgsql` needs no source change. Its `PostgresIntegrationTest` consumes the schema and must be migrated.
- Axis priority remains per-`#[Scoped]`-attribute declaration order — no registry-level priority is introduced.
- No duplicate-axis error: axes are open for extension by any package; loud errors fire on dangling references (unknown axis, unknown scope, default-scope write).

## Scope

### In Scope
- Add `default` property to `ScopeAxis`.
- Add new `ScopeConfigurationException` factories for the new validation rules.
- Rewrite `PhpScopeRegistry::buildAxes()` to parse `scopes` (associative map) + `default` (string).
- Create `packages/scope/config/scope.php` shipping `locale` (`default`), `market` (`default`), and `channel` (`web`).
- Change `SignatureCandidateEnumerator::enumerate()` to filter axis defaults from `walkUp()` results.
- Apply the same filter inside `ScopeWalker::findFirstMatch()` for the `walkAt`/`resolvedAt` path.
- Add `InvalidSignatureForAttributeException::forDefaultScope()` and have `ScopeSignatureValidator` reject any axis-at-default in a signature.
- Introduce `Markommerce\Scope\Storage\DefaultScopeGuard` and have `HasScopes::setOverride()` / `clearOverride()` call it so direct trait writes at a default-scope signature also throw. Wire it via a `boot` callback in `packages/scope/module.php`.
- Update every existing test that constructs `new ScopeAxis(...)` or passes `['hierarchy' => …]` config so the suites stay green.
- Update `packages/scope-pgsql/tests/Feature/PostgresIntegrationTest.php` to the new schema and verify the plain-SQL optimization.
- Update `packages/scope/CHANGELOG.md` `[Unreleased]` section.

### Out of Scope
- Backward-compatibility with the old `hierarchy` config key (the package is pre-release; hard break).
- Per-scope metadata beyond the empty-array placeholder (`'scopes' => ['web' => []]`).
- Auto-initialization of `ScopeContext` to declared defaults (the enumerator collapse makes unset == default; no init needed).
- Orphan-dotted-path validation (declaring `de.at` without `de`).
- A `boot`-time programmatic axis registrar (config-file mechanism is sufficient).
- Cross-axis priority via a registry-level `priority` integer (per-attribute order already covers this).
- Catalog package work — catalog is a future consumer, not part of this plan.
- Docs/README content (handled by the `doc-updater` post-implementation pipeline).

## Success Criteria
- [ ] `packages/scope/config/scope.php` ships and is loaded by `PhpScopeRegistry` without error.
- [ ] A second package's `config/scope.php` adding either a new axis or an extra scope to an existing axis merges additively (no clobbering).
- [ ] When `ScopeContext` is empty (or every active axis is at its declared default), `ScopedOrderBy` emits `ORDER BY "<column>"` with no `scopes->` access in the SQL.
- [ ] `ScopeResolver::resolvedAt(..., signature-at-default)` falls through to the base column value even when an override exists at `axis:default` in storage (the `findFirstMatch` filter).
- [ ] Setting an override at a default-scope signature **through `ScopeResolver`** throws `InvalidSignatureForAttributeException`.
- [ ] Setting an override at a default-scope signature **directly through the `HasScopes` trait** throws `ScopeStorageException` (via `DefaultScopeGuard` configured at module boot).
- [ ] All `scope` and `scope-pgsql` test suites pass under `composer test` and `composer test:all`.
- [ ] `CHANGELOG.md` `[Unreleased]` lists the breaking config change.
- [ ] Code follows project standards (`phpcs`, `phpstan` level 8 clean).

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Extend ScopeAxis with a default scope and add configuration exception factories | - | completed |
| 002 | Parse the multi-axis scopes-map configuration schema in PhpScopeRegistry | 001 | completed |
| 003 | Ship default locale, market, and channel axes as packages/scope/config/scope.php | 002 | completed |
| 004 | Collapse default-resolved axes in SignatureCandidateEnumerator and ScopeWalker::findFirstMatch | 001 | completed |
| 005 | Reject overrides written at a default scope in ScopeSignatureValidator | 001 | completed |
| 006 | Verify default-scope resolution end-to-end and add the scope changelog entry | 002, 003, 004, 005 | completed |
| 007 | Update scope-pgsql integration tests for the scopes-map schema and verify plain SQL | 002, 003, 004 | completed |
| 008 | Reject default-scope writes at the storage trait level via DefaultScopeGuard | 001, 002 | completed |

## Architecture Notes

- **`ScopeAxis` stays a `readonly class` value object** with three public properties: `name`, `hierarchy`, `default`. No method on the registry interface needs to change — `getAxis()->default` exposes the new field.
- **Schema:**
  ```php
  'axes' => [
      'locale'  => ['default' => 'default', 'scopes' => ['default' => []]],
      'market'  => ['default' => 'default', 'scopes' => ['default' => []]],
      'channel' => ['default' => 'web',     'scopes' => ['web' => []]],
  ]
  ```
  Scope-map values are reserved empty arrays for now.
- **`ScopeHierarchy` is unchanged** — `PhpScopeRegistry` builds it via `ScopeHierarchy::fromPaths(array_keys($scopes))`. PHP preserves associative-array insertion order, and `ConfigMerger` appends new keys after existing ones, so declaration order is stable.
- **Two parallel filter sites apply the same rule**:
  - `SignatureCandidateEnumerator::enumerate()` — the `walk()` / `resolved()` / `ScopedOrderBy` path:
    ```php
    $axisDefault = $registry->getAxis($axis)->default;
    $walked = $registry->getHierarchy($axis)->walkUp($path);
    $walked = array_values(array_filter($walked, fn (string $p): bool => $p !== $axisDefault));
    $axisValues[$axis] = array_merge($walked, [null]); // [null] = OMIT-only when filtered empty
    ```
  - `ScopeWalker::findFirstMatch()` — the `walkAt()` / `resolvedAt()` path (does **not** go through the enumerator). Without filtering here, a stored override at `axis:default` (writable today via the storage trait's `setOverride()`, which bypasses `ScopeSignatureValidator`) would still resolve through `resolvedAt`, breaking the "default scope ≡ base column" invariant. Apply the same `array_filter($walked, fn $p => $p !== $axisDefault)` step before the override-lookup `array_find`.
- **`ScopeContext` is unchanged**. Setting `in('locale', 'default')` remains valid (the default is a declared scope), and an unset axis is already OMIT — the enumerator filter makes both cases equivalent.
- **`ScopeResolver`/`ScopedOrderBy` get the optimization for free** via the enumerator returning `[]`. `ScopeWalker::walkAt` (and therefore `resolvedAt`) needs an explicit filter; see task 004.
- **`DefaultScopeGuard` is an architectural concession.** The `HasScopes` trait cannot have constructor injection (PHP traits) and CLAUDE.md forbids service locators, so the trait cannot fetch the registry at write time. `DefaultScopeGuard` is a small purpose-built class with static `array<string,string>` state (axis → default) set once by `module.php`'s `boot` callback. This is configuration, not runtime container lookup — the static is isolated to a single named file and never pulled from a container. The trait calls `DefaultScopeGuard::assertWritable($signature)` and gets a loud `ScopeStorageException` on a default-scope write. The guard is lenient (no-op) when unconfigured so existing unit tests of unrelated entities using `HasScopes` don't need to wire it.

## Risks & Mitigations
- **Marko `ConfigDiscovery` may not auto-load `packages/scope/config/scope.php` in every host application** → task 003 includes an explicit `PhpScopeRegistry`-level acceptance test that loads the file by `require` and feeds it to the registry, proving its shape is valid. Auto-discovery wiring is a Marko-framework concern outside this plan.
- **Test fakes constructing `new ScopeAxis(...)` exist in ~8 files across `scope` and one in `scope-pgsql`** → task 001 owns the ripple inside `scope`; task 007 owns the scope-pgsql ripple. Between 001 completion and 007 completion, `composer test:all` is red on `scope-pgsql`'s integration test only — `composer test` (default) stays green throughout. **Tasks 001–006 use `composer test` as their acceptance gate, not `composer test:all`**; only task 007 must run under `composer test:all` (it owns the integration-destructive update).
- **`getAxis()`-throwing fakes are immune to task 001 but NOT to task 005** → `ScopeSignatureValidatorTest`, `ScopeMetadataFactoryTest`, and `ScopedEntityValidatorTest` have fakes that throw `RuntimeException('Not implemented')` from `getAxis()`. Tasks 001–004 don't touch validator paths that need `getAxis()`. Task 005 introduces `getAxis($axis)->default` inside `ScopeSignatureValidator::validate()`, which means `ScopeSignatureValidatorTest`'s fake must be rewritten to return real `ScopeAxis` instances. Task 005's file list now spells this out explicitly.
- **Two packages inventing the same axis name silently deep-merge** (no loud error possible with `ConfigMerger`'s post-merge view) → accepted; namespacing axis names is a future cosmetic fix if it ever happens in practice.
- **`isAssociative([])` is `false`**, so two sources both declaring `'web' => []` fall through ConfigMerger's `else` branch and the value is replaced (last-wins) rather than recursed → benign for the empty-array placeholder; once scopes carry metadata, sources adding metadata to a previously-empty scope must use a non-empty array.
- **`walkAt`/`resolvedAt` is a separate code path from the enumerator** → `ScopeWalker::findFirstMatch()` calls `walkUp()` directly without the enumerator's filter. The storage trait's `setOverride()` (called directly, not through `ScopeResolver`) bypasses the validator and can persist a `locale:default` override. Without filtering in `findFirstMatch()`, that override would be returned by `resolvedAt`, contradicting the invariant. Task 004 now extends the same filter into `ScopeWalker::findFirstMatch()`.
- **Existing unit-test scope strings (`'global'`, `'eu'`, `'es'`, `'b2b'`, …) frequently sit at index 0 of their fake axes' path lists** → choosing "first path is the axis default" as the auto-default convention for fake registries would silently make all those overrides unreachable under task 004's filter, hiding regressions. Task 001 mandates a **sentinel default convention** instead: each fake-registry helper prepends `'__test_default'` to each axis's hierarchy and sets it as the default unless the caller passes an explicit default via a new `$defaults` parameter. Tasks 004/005/007 all adopt this convention.
- **`DefaultScopeGuard` static state could bleed across tests within the same process** → task 008 mandates `DefaultScopeGuard::reset()` in `afterEach()` for every test that calls `configure()`. Pest 4 parallel mode isolates *files* in separate processes, so cross-file bleed is impossible; within-file bleed is the only risk and is handled by the reset hook.
