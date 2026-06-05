# Task 005: Page value object (sequential floor)

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the immutable `Page` value object — the universal pagination result. It carries the hydrated items, the page size, and opaque `nextPosition`/`previousPosition` tokens, and answers `hasNext()`. This is the floor every strategy returns; it deliberately has NO total or page number (random access is the optional `RandomAccessPageInterface`).

## Context
- File: `packages/criteria/src/Page/Page.php` (namespace `Markommerce\Criteria\Page`).
- `readonly class Page` with `@template TEntity`: `EntityCollection $items`, `int $size`, `?string $nextPosition`, `?string $previousPosition`.
- `EntityCollection` is `Marko\Database\Entity\EntityCollection` (verified). `Page` therefore depends on `marko/database` — already declared in Task 001's composer.json.
- `hasNext(): bool` returns `nextPosition !== null`; add `hasPrevious(): bool` for symmetry.
- No total, no current page — those belong to `RandomAccessPageInterface` only.

## Requirements (Test Descriptions)
- [x] `it exposes its items collection and page size`
- [x] `it reports hasNext true when a next position is present`
- [x] `it reports hasNext false when there is no next position`
- [x] `it reports hasPrevious based on the previous position`
- [x] `it exposes the opaque next and previous position tokens`

## Acceptance Criteria
- All requirements have passing tests.
- `Page` is readonly and generic via PHPDoc.
- No decrease in coverage.

## Implementation Notes
- `Page` created as `readonly class` with `@template TEntity of Entity` (constrained to satisfy PHPStan level 8 generics).
- All 5 tests pass; PHPStan reports no errors on `packages/criteria`.
- The pre-existing `PageRequestTest` failure (`InvalidPageSizeException` context/suggestion empty) is unrelated to this task.
