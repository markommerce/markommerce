# Task 014: `CategoryTreeService` — market assignment and resolution

**Status**: completed
**Depends on**: 013
**Retry count**: 0

## Description
Extend `CategoryTreeService` with market-to-tree assignment and resolution. A market without an explicit assignment falls back to the default tree. `resolveTreeForMarket` throws when no default exists.

## Context
- Modify existing file: `packages/catalog/src/Services/CategoryTreeService.php`
- New methods:
  - `assignTreeToMarket(int $treeId, string $market): void` — throws `CategoryTreeNotFoundException`; replaces any existing assignment for that market (upsert semantics from task 009/012)
  - `unassignMarket(string $market): void` — removes the assignment for the market; safe if no assignment exists (idempotent)
  - `resolveTreeForMarket(string $market): CategoryTree` — looks up by market; falls back to default; throws `DefaultTreeMissingException` if neither exists
- Test file: `packages/catalog/tests/Unit/Services/CategoryTreeServiceMarketResolutionTest.php`
- Uses fakes from tasks 007 and 009

## Requirements (Test Descriptions)
- [ ] `assignTreeToMarket stores the assignment for an unknown market`
- [ ] `assignTreeToMarket replaces an existing assignment for the same market`
- [ ] `assignTreeToMarket throws CategoryTreeNotFoundException when the tree id is unknown`
- [ ] `unassignMarket removes the assignment for the given market`
- [ ] `unassignMarket is a no-op when no assignment exists for the market`
- [ ] `resolveTreeForMarket returns the tree assigned to the given market`
- [ ] `resolveTreeForMarket returns the default tree when no assignment exists for the market`
- [ ] `resolveTreeForMarket throws DefaultTreeMissingException when neither a market assignment nor a default tree exist`

## Acceptance Criteria
- Service methods added without breaking existing tests
- All previously-added tests under `packages/catalog/tests/Unit/Services/CategoryTreeService*Test.php` continue to pass with the unchanged constructor (no new dependencies added in this task)
- `@throws` PHPDoc tags on every throwing path
- PHPStan level 8 clean

## Implementation Notes
- Validate the tree exists by calling `$this->categoryTreeRepository->find($treeId)` (throw `CategoryTreeNotFoundException::forId($treeId)` when null) BEFORE constructing the `CategoryTreeMarketAssignment` and saving it.
- Consider extracting a shared `tests/Support/CategoryTreeServiceTestHelper.php` (or a Pest `beforeEach`) that constructs the service with all currently-known fakes, so that later tasks (015, 016, 017) can extend it without rewriting every test.
