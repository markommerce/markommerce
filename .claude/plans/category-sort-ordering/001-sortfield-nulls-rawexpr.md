# Task 001: SortField raw-expression + NULLS placement (criteria)

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Extend the `criteria` package so a sort field can carry an optional raw SQL expression and an explicit NULLS placement, and make the pagination strategies emit it via `orderByRaw`. This is the foundation that lets price ordering push non-indexed (NULL-priced) products last in both directions, and lets the market layer order by a `COALESCE(...)` expression. All additions are backward compatible.

## Context
- Related files:
  - `packages/criteria/src/Sort/SortField.php` (currently `readonly class { string $column; SortDirection $direction = Ascending }`)
  - `packages/criteria/src/Sort/SortDirection.php` (`ASC`/`DESC` enum)
  - `packages/criteria/src/Strategy/OffsetPaginationStrategy.php` (applies `$query->orderBy($field->column, $field->direction->value)` then `$query->orderBy('id', 'ASC')`)
  - `packages/criteria/src/Strategy/KeysetPaginationStrategy.php`
- Marko query builder API (`marko/packages/database/src/Repository/RepositoryQueryBuilder.php`): `orderBy(string $column, string $direction='ASC')` and `orderByRaw(string $expression, string $direction='ASC')`.
- **CRITICAL — there is NO `NULLS LAST` support in the query builder.** Verified against `marko/packages/database-pgsql/src/Query/PgSqlQueryBuilder.php`: `buildOrderByClause()` (line ~930) emits only `"<column-or-expr> <direction>"` where `<direction>` is forced to exactly `ASC` or `DESC` (anything else is coerced to `ASC`, line ~402-405). You CANNOT pass `"ASC NULLS LAST"` as the direction (it becomes `ASC`), and you CANNOT append `NULLS LAST` to the expression because the builder always appends ` <direction>` after it (e.g. expr `"amount ASC NULLS LAST"` compiles to `amount ASC NULLS LAST ASC` — invalid SQL).
- **NULLS-last mechanism: emit a companion "IS NULL" sort field first.** To push NULLs last in BOTH directions without the `NULLS LAST` keyword, the consuming order must contribute TWO sort fields: a raw-expression field `(<column-or-expr>) IS NULL` ordered `ASC` (false=0 sorts before true=1, so non-NULLs come first), followed by the real `<column-or-expr> <direction>` field. `NullsPlacement` on a single `SortField` is therefore a *declarative hint*: when set, the strategy must expand that one field into the two `orderByRaw` clauses described above. Decide and document in this task whether expansion happens (a) inside the strategy when it sees `nulls !== null`, or (b) by the sort order returning two explicit `SortField`s — pick (a) so callers declare intent once and both strategies behave identically. The companion `IS NULL` expression passes `IdentifierValidator::assertNoDangerousPatterns` (denylist is only `;`, `--`, `/* */`, backtick).
- `orderByRaw` accepts `COALESCE(...)`, `->'market:xx'->>'amount'`, `::numeric`, and `(... IS NULL)` — all clear the denylist. Confirmed via `marko/packages/database/src/Query/IdentifierValidator.php`.
- Patterns to follow: existing readonly value objects in `packages/criteria/src/Sort`.

## Requirements (Test Descriptions)
- [x] `it defaults to no nulls placement and no raw expression for a plain column sort field`
- [x] `it accepts an explicit nulls last placement`
- [x] `it accepts a raw expression instead of a plain column`
- [x] `it exposes the raw expression when one is set and falls back to the column otherwise`
- [x] `it applies a plain column sort field via orderBy when no raw expression or nulls placement is set`
- [x] `it expands a nulls-last sort field into an IS NULL companion clause followed by the real ordering in the offset strategy`
- [x] `it keeps the IS NULL companion ascending so non-null values sort first in both directions` (assert against compiled SQL or a query-builder spy: companion is always `... IS NULL ASC` regardless of the real field direction)
- [x] `it applies a raw-expression sort field via orderByRaw in the offset strategy`
- [x] `it expands a nulls-last sort field the same way in the keyset strategy`
- [x] `it preserves the id ascending tie-break after applying sort fields`
- [x] `it keeps existing two-argument SortField construction working unchanged`

## Acceptance Criteria
- New constructor parameters (e.g. `?string $expression`, `?NullsPlacement $nulls`) are optional with defaults that reproduce current behavior.
- A `NullsPlacement` enum (`First`/`Last`) is added under `packages/criteria/src/Sort`.
- `SortField` exposes a single accessor (e.g. `sortExpression(): string`) returning `$expression ?? $column`, used by the strategies so callers don't branch.
- BOTH `OffsetPaginationStrategy` AND `KeysetPaginationStrategy` are updated identically (the keyset strategy is NOT out of scope — it must expand `NullsPlacement` the same way, even though v1 orders are offset-only, so the machinery is correct when a keyset-capable order is added later). When `nulls !== null` they emit a companion `orderByRaw("(<sortExpression>) IS NULL", 'ASC')` for `Last` (or `'DESC'` for `First`) BEFORE the real ordering; when an expression is present (no nulls) they use `orderByRaw(<expression>, direction)`; plain `orderBy` otherwise. The `id ASC` tie-break is unchanged and stays last.
- The keyset seek predicate / `ProductCursorValueExtractor` interaction is unaffected because v1 keyset-capable orders contribute no raw/nulls fields; no need to teach the extractor about raw expressions in this task.
- All existing criteria tests still pass (existing `SortField(column, direction)` call sites compile and behave identically); PHPStan level 8 clean.

## Implementation Notes
- Added `NullsPlacement` enum (`First`/`Last`) at `packages/criteria/src/Sort/NullsPlacement.php`.
- Extended `SortField` with optional `?string $expression` and `?NullsPlacement $nulls` constructor parameters (both default to `null`). Added `sortExpression(): string` accessor returning `$expression ?? $column`.
- Extracted `SortFieldApplier` readonly class at `packages/criteria/src/Strategy/SortFieldApplier.php` to eliminate duplication between the two strategies. Both strategies inject it via constructor default `new SortFieldApplier()` — no DI registration needed.
- Both `OffsetPaginationStrategy` and `KeysetPaginationStrategy` now delegate sort-field application to `SortFieldApplier::apply()`: plain fields use `orderBy`, expression-only fields use `orderByRaw`, nulls-placement fields emit a companion `(<expr>) IS NULL ASC`/`DESC` first then the real `orderByRaw`. The `id ASC` tie-break is unchanged.
- All 67 criteria tests pass. PHPStan level 8 clean. The pre-existing 13 Vite/layout failures are unrelated.
