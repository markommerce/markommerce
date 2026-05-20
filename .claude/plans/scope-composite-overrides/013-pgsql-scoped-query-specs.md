# Task 013: ScopedOrderBy Rewrite (composite-signature COALESCE chain)

**Status**: complete
**Depends on**: 002, 010, 011
**Retry count**: 0

## Description
Rewrite `ScopedOrderBy` to use the new `SignatureCandidateEnumerator` + `ScopedFieldRendererInterface`. The shared enumerator output guarantees the PHP walker (task 004) and the SQL builder produce identical resolution semantics for the same context + attribute.

**Out of scope**: `ScopedSelect` and `ScopedWhere` are dropped from this plan — they require `selectRaw` / `whereRaw` methods on `Marko\Database\Query\QueryBuilderInterface`, which do not exist today (only `orderByRaw` and `raw` are defined). Adding raw select/where to marko/database is a follow-up plan. See `_plan.md` "Out of Scope".

## Context
- Files:
  - `packages/scope/src/Query/ScopedOrderBy.php` (REWRITTEN — uses enumerator + field renderer).
  - `packages/scope/src/Query/ScopedOrderByFactory.php` (signature changed — now takes `SignatureCandidateEnumerator` and `ScopedFieldRendererInterface` instead of `ScopeSortRendererInterface`).
  - `packages/scope/tests/Unit/Query/ScopedOrderByTest.php` (UPDATED — `makeRenderer()` helper builds a fake `ScopedFieldRendererInterface`; the spec constructor takes the new types).
  - `packages/scope/tests/Unit/Query/ScopedOrderByFactoryTest.php` (UPDATED — `makeFactoryRenderer()` returns a `ScopedFieldRendererInterface` fake; the factory constructor takes the new types).
- `ScopedOrderBy` rewrite:
  ```
  apply(EntityQueryBuilderInterface $builder):
      assertPropertyScoped()
      attributeAxes = scopeMetadataFactory.for($entityClass).axesForProperty($property)
      candidates = signatureCandidateEnumerator.enumerate(attributeAxes, scopeContext)
      if candidates === []: builder.orderBy($property, strtoupper($direction)); return
      expr = new ScopedFieldExpression(property: $property, column: $property, candidateSignatures: candidates)
      sql = scopedFieldRenderer.render(expr)
      builder.orderByRaw(sql, strtoupper($direction))
  ```
- The factory's constructor changes from `(ScopeMetadataFactory, ScopeContext, ScopeSortRendererInterface)` to `(ScopeMetadataFactory, ScopeContext, SignatureCandidateEnumerator, ScopedFieldRendererInterface)`. Both factory and spec are registered in `packages/scope/module.php`.
- The enumerator is added as a constructor dep on the factory (and forwarded to the spec).
- `ScopedSelect`, `ScopedSelectFactory`, `ScopedWhere`, `ScopedWhereFactory` are NOT created in this task. They are explicitly deferred.

## Requirements (Test Descriptions)

### ScopedOrderBy (rewrite)
- [x] `it builds a COALESCE-based orderByRaw using the candidate signatures from the enumerator`
- [x] `it falls back to plain orderBy when the enumerator produces zero candidates`
- [x] `it produces signatures in descending-score order in the COALESCE chain (the order is preserved as enumerator output)`
- [x] `it throws ScopeContextException when the property is not @Scoped on the entity`
- [x] `it passes ASC or DESC direction to the query builder unchanged`
- [x] `it does NOT call HasScopesInterface::overrides() at all during apply (the SQL path never reads stored override keys; it derives the chain from the enumerator only)`

### ScopedOrderByFactory
- [x] `it constructs a ScopedOrderBy with the new enumerator and field renderer dependencies`
- [x] `it forwards the entity class, property, and direction to the spec`

### Cap warning end-to-end (in ScopedOrderBy test)
- [x] `it emits the cap-exceeded warning once when the candidate count exceeds the configured cap (verified by injecting a low-cap enumerator)`

### COALESCE chain matches PHP walker iteration order
- [x] `for the same attribute axes and context, the JSONB keys appearing in the COALESCE chain match the order in which the walker iterates candidates (no algorithmic drift)`

## Acceptance Criteria
- All requirements have passing tests.
- `ScopedOrderByFactory` is registered as a singleton in `packages/scope/module.php` with the new dependencies wired.
- The COALESCE chain order is identical to the PHP walker's iteration order (verified by a test that constructs both and asserts the JSONB keys appear in the same order as the walker's candidate enumeration).
- No `ScopedSelect*` or `ScopedWhere*` files are created in this task.
- `composer test` is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes

- The `ScopedOrderBy` and `ScopedOrderByFactory` implementations were already rewritten in task 010. This task added the required test coverage.
- Added `orderByRaw()` to `Marko\Database\Query\QueryBuilderInterface` and implemented it in `RepositoryQueryBuilder` and `PgSqlQueryBuilder` (with `raw: bool` flag on the orders array) to fix the pre-existing phpstan error from task 010.
- All 10 new test descriptions added to `ScopedOrderByTest.php` and `ScopedOrderByFactoryTest.php`.
- `ScopeWalker` import added to `ScopedOrderByTest.php` for the COALESCE-chain-vs-walker test.
- `ScopedOrderByMultiAxisProduct` fixture class added for the cap-exceeded warning test.
- phpcs issues fixed via `phpcbf` (multi-line method signatures in anonymous class).
