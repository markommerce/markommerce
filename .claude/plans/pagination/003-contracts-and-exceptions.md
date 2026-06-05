# Task 003: Engine contracts + loud exceptions

**Status**: complete
**Depends on**: 001, 002, 005
**Retry count**: 0

## Description
Define the engine's public contracts — `PaginationStrategyInterface`, `RandomAccessPageInterface`, `RowCounterInterface` — and the remaining loud exceptions: `IncompatiblePositionException` and `PageOutOfRangeException`. Interfaces carry full PHPDoc generics and `@throws`; the testable behavior in this task is the exceptions.

## Context
- Files: `packages/criteria/src/Contracts/PaginationStrategyInterface.php`, `RandomAccessPageInterface.php`, `RowCounterInterface.php`, `CursorValueExtractorInterface.php` (namespace `Markommerce\Criteria\Contracts`); `packages/criteria/src/Exceptions/IncompatiblePositionException.php`, `PageOutOfRangeException.php` (namespace `Markommerce\Criteria\Exceptions`).
- **DEPENDS on 002 (`PageRequest`) and 005 (`Page`)** because the interface signatures reference both — they already exist when this task runs. Under PHPStan level 8 every referenced type must exist, so do NOT forward-reference unbuilt classes.
- **Define `CursorValueExtractorInterface` HERE** (in the Contracts dir) to avoid a circular dependency with Task 008 (the keyset strategy implements against it but must not own the contract). Shape: `extract(object $entity, Sort $sort): array<string, scalar>` — returns the boundary entity's sort-key values + id for cursor encoding. Task 008 consumes this interface; catalog (Task 012) provides a concrete extractor.
- **CANONICAL SIGNATURE (binding for Tasks 006, 008, 009, 012 — do not diverge):**
  ```php
  /**
   * @param RepositoryQueryBuilder<TEntity> $query
   * @return Page<TEntity>
   * @throws IncompatiblePositionException
   */
  public function paginate(
      RepositoryQueryBuilder $query,
      PageRequest $pageRequest,
      ?CursorValueExtractorInterface $cursorValueExtractor = null,
  ): Page;
  ```
  The optional third param is **ignored by the offset strategy** and **required by the keyset strategy** (keyset throws a loud exception if it is null when a non-first position is present). This single signature keeps one resolvable interface and one default container binding (Task 009) — no per-construction extractor.
- `RandomAccessPageInterface<TEntity>`: `currentPage(): int`, `totalPages(): int`, `totalItems(): int`, `positionForPage(int $page): string` (`@throws PageOutOfRangeException`).
- `RowCounterInterface`: `count(RepositoryQueryBuilder $query): int`.
- Exceptions extend `MarkoException` with static factories: e.g. `IncompatiblePositionException::expected(string $strategy, string $tokenType)`, `PageOutOfRangeException::forPage(int $requested, int $totalPages)`.
- Generics via PHPDoc `@template`; `RepositoryQueryBuilder` from `marko/database`.

## Requirements (Test Descriptions)
- [x] `it builds an IncompatiblePositionException naming the expected and actual token type`
- [x] `the IncompatiblePositionException carries context and a suggestion to restart from the first page`
- [x] `it builds a PageOutOfRangeException naming the requested page and total pages`
- [x] `the PageOutOfRangeException carries context and a suggestion`
- [x] `the strategy and counter interfaces declare the documented method signatures`

## Acceptance Criteria
- All requirements have passing tests (interface signatures asserted via reflection or a test double).
- Exceptions extend `MarkoException` with message/context/suggestion.
- No decrease in coverage.

## Implementation Notes
- Created `packages/criteria/src/Exceptions/IncompatiblePositionException.php` with `expected(string $strategy, string $tokenType): self` factory; suggestion explicitly advises restarting from the first page.
- Created `packages/criteria/src/Exceptions/PageOutOfRangeException.php` with `forPage(int $requested, int $totalPages): self` factory.
- Created `packages/criteria/src/Contracts/PaginationStrategyInterface.php` with canonical `paginate()` signature; `@template TEntity of Entity`; `RepositoryQueryBuilder` not generic in PHPDoc (class itself has no generics).
- Created `packages/criteria/src/Contracts/RowCounterInterface.php` with `count(RepositoryQueryBuilder $query): int`.
- Created `packages/criteria/src/Contracts/RandomAccessPageInterface.php` with `currentPage`, `totalPages`, `totalItems`, `positionForPage(int $page): string` (throws `PageOutOfRangeException`).
- Created `packages/criteria/src/Contracts/CursorValueExtractorInterface.php` with `extract(object $entity, Sort $sort): array`.
- PHPStan level 8 clean on source; tests use `assert(... instanceof ReflectionNamedType)` to satisfy null-safety checks.
