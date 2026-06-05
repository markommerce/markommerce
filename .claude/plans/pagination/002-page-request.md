# Task 002: PageRequest value object + page-size guard

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the immutable `PageRequest` value object — the request for one page — with `size`, a `Sort`, and an opaque nullable `position` token, exposed via `first()` and `at()` factories. Add the loud `InvalidPageSizeException` for non-positive sizes.

## Context
- Files: `packages/criteria/src/Page/PageRequest.php` (namespace `Markommerce\Criteria\Page`), `packages/criteria/src/Exceptions/InvalidPageSizeException.php` (namespace `Markommerce\Criteria\Exceptions`).
- `position` is an opaque string token (null = first page). It is NOT interpreted here — strategies decode it (Task 004/006/008).
- `InvalidPageSizeException extends MarkoException` with `message`/`context`/`suggestion` static factory (see `marko/core` `MarkoException` and `packages/catalog/src/Exceptions/ProductNotFoundException.php` for the pattern).
- Patterns: `readonly class`, private constructor + named static factories, `@throws` on factories that validate.

## Requirements (Test Descriptions)
- [x] `it creates a first-page request with a null position`
- [x] `it creates a positioned request carrying an opaque token`
- [x] `it exposes the requested size and sort`
- [x] `it rejects a size of zero with a loud InvalidPageSizeException`
- [x] `it rejects a negative size with a loud InvalidPageSizeException`
- [x] `the InvalidPageSizeException carries message, context and suggestion`

## Acceptance Criteria
- All requirements have passing tests.
- Exception extends `MarkoException` and exposes context + suggestion.
- No decrease in coverage.

## Implementation Notes
- `PageRequest`: `readonly class` with private constructor, `first()` and `at()` static factories, both calling `guardSize()` private helper before construction.
- `InvalidPageSizeException`: extends `MarkoException`, `forSize(int $size)` static factory with message/context/suggestion.
- `MarkoException` exposes `getContext()` / `getSuggestion()` (not public properties), so tests use those methods.
- All 17 criteria tests pass; PHPStan level 8 reports no errors.
