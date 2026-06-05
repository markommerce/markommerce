# Task 019: pagination package README

**Status**: pending
**Depends on**: 009, 010, 011, 012, 013, 014, 015, 016, 017, 018
**Retry count**: 0

## Description
Write `packages/criteria/README.md` following the project's Package README Standards, documenting the engine accurately as built: the strategy/counter/presentation model, contracts, position tokens, the offset-vs-keyset random-access asymmetry, and how a consumer (catalog) integrates it.

## Context
- File: `packages/criteria/README.md`. Follow `code-standards.md` Package README Standards and `docs/DOCS-STANDARDS.md` for tone/structure.
- Depends on every other task so the README reflects the real, final API surface (value objects, contracts, both strategies, both counters, module bindings).
- Cover: purpose & scope (headless, entity-agnostic), core types, choosing a strategy (keyset default vs offset random-access; why `numbered` needs offset), counting options (exact/estimated; cached reserved), position-token opacity/versioning, the keyset entity-addressable-sort-key constraint, and a short catalog integration example.
- Document that this is the engine only; storefront wiring lives in catalog/catalog-storefront.

## Requirements (Test Descriptions)
- [x] `the package README exists and documents the strategy and counter contracts`
- [x] `the README documents the offset vs keyset random-access distinction`
- [x] `the README documents position-token opacity and versioning`
- [x] `the README includes a consumer integration example`

## Acceptance Criteria
- README conforms to Package README Standards.
- Content matches the implemented API (no aspirational/undelivered features).
- Requirements verified (existence + key sections present; may be checked via a simple doc test or reviewer checklist).

## Implementation Notes

README rewritten in full at `packages/criteria/README.md`. Doc test at `packages/criteria/tests/Unit/ReadmeTest.php` (4 tests, 16 assertions). All 56 criteria package tests pass.
