# Task 011: PgSqlScopedFieldRenderer

**Status**: complete
**Depends on**: 010
**Retry count**: 0

## Description
Implement `PgSqlScopedFieldRenderer` — the Postgres-specific implementation of `ScopedFieldRendererInterface`. Replaces `PgSqlScopeSortRenderer`. Emits a `COALESCE` chain over JSONB key lookups for each candidate signature, ending in the plain fallback column.

## Context
- New file: `packages/scope-pgsql/src/Query/PgSqlScopedFieldRenderer.php`.
- Delete: `packages/scope-pgsql/src/Query/PgSqlScopeSortRenderer.php` and its test `packages/scope-pgsql/tests/Unit/Query/PgSqlScopeSortRendererTest.php`.
- Update `packages/scope-pgsql/module.php` binding: `ScopedFieldRendererInterface::class => PgSqlScopedFieldRenderer::class`.
- SQL emitted (example for property `name`, column `name`, candidates `[channel:b2b|locale:es.es, channel:b2b|locale:es, channel:b2b, locale:es.es, locale:es]`):
  ```sql
  COALESCE(
      "scopes"->'channel:b2b|locale:es.es'->>'name',
      "scopes"->'channel:b2b|locale:es'->>'name',
      "scopes"->'channel:b2b'->>'name',
      "scopes"->'locale:es.es'->>'name',
      "scopes"->'locale:es'->>'name',
      "name"
  )
  ```
- Empty signatures list → emit `"<column>"` alone (no COALESCE).
- Identifier validation:
  - `$expression->column`, `$expression->property`, `$expression->jsonColumn` must each pass `IdentifierValidator::isValidIdentifier()` (else `InvalidColumnException`).
  - For each signature, split each axis name and each path segment (the dot-separated parts of the value) and validate every one. (Same approach as the old renderer.)
- This task ALSO updates `packages/scope-pgsql/tests/Unit/ModuleTest.php` to reflect the new binding (the binding map test currently asserts `ScopeSortRendererInterface`).

## Requirements (Test Descriptions)
- [x] `it renders a single-axis signature as a single JSONB lookup wrapped in COALESCE with the fallback column`
- [x] `it renders a multi-signature list as COALESCE in the exact order the signatures appear`
- [x] `it renders a composite signature key as alphabetical-axis-sorted pipe-joined form (channel:b2b|locale:es)`
- [x] `it returns just the quoted fallback column when the candidate list is empty`
- [x] `it embeds path segments containing dots correctly into JSONB keys (e.g. eu.de)`
- [x] `it throws InvalidColumnException when the fallback column is not a valid identifier`
- [x] `it throws InvalidColumnException when the property is not a valid identifier`
- [x] `it throws InvalidColumnException when the jsonColumn is not a valid identifier`
- [x] `it throws InvalidColumnException when a signature axis name is not a valid identifier`
- [x] `it throws InvalidColumnException when a signature value segment is not a valid identifier`
- [x] `it does not append ORDER BY direction (the caller adds that)`
- [x] `it does not append SELECT alias (the caller adds that)`
- [x] `it emits COALESCE branches in the exact order of the expression's candidateSignatures (no re-sorting, no de-duplication)`
- [x] `it constructs each JSONB key only AFTER every axis name and every path segment has been validated as a safe identifier (validation precedes string concatenation)`
- [x] `it throws InvalidColumnException when a composite signature contains an axis name that fails IdentifierValidator`
- [x] `it does NOT iterate stored overrides (the COALESCE chain length is exactly count(candidate signatures) + 1 for the fallback column — verified by counting COALESCE arguments)`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/scope-pgsql/module.php` binds `ScopedFieldRendererInterface` → `PgSqlScopedFieldRenderer`.
- Old `PgSqlScopeSortRenderer` file deleted; old test deleted.
- `ModuleTest.php` updated to assert the new binding.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
- Created `PgSqlScopedFieldRenderer` as a direct replacement for `PgSqlScopeSortRenderer`.
- The new renderer adds axis name and value segment validation (splitting values on `.` and validating each segment) before building the COALESCE chain.
- Validation is performed in a separate `validateSignatures()` pass before any SQL string concatenation, satisfying the "validation precedes concatenation" requirement.
- Deleted `PgSqlScopeSortRenderer.php`, `PgSqlScopeSortRendererTest.php`, and updated `module.php`, `ModuleTest.php`, `SourceTreeTest.php`, and `CopiedTestTreeTest.php` to reference the new class.
- All 16 requirements have passing tests. `phpcs`, `php-cs-fixer`, and `phpstan` are clean.
