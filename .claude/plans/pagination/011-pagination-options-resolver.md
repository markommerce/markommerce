# Task 011: Catalog pagination options resolver

**Status**: pending
**Depends on**: 009, 010
**Retry count**: 0

## Description
Create `PaginationOptionsResolver` in catalog: it reads `CatalogPaginationConfig`, turns raw request params (`page`/`size`/`sort`) into a clamped, validated `PageRequest`, resolves the strategy KIND + count mode from config (instances are built in Task 012), and validates the strategy×presentation combination loudly. Returns a `ResolvedPaginationOptions` DTO.

## Context
- Files: `packages/catalog/src/Pagination/PaginationOptionsResolver.php`; enums `PaginationPresentation` (`numbered`|`load_more`|`infinite`), `PaginationStrategyKind` (`offset`|`keyset`), `CountMode` (`exact`|`estimated`); a readonly result DTO `ResolvedPaginationOptions`; and `packages/catalog/src/Exceptions/InvalidPaginationConfigException.php` + `packages/catalog/src/Exceptions/PageDepthExceededException.php`.
- **This resolver is config→options ONLY. It injects just `ConfigResolverInterface`** (param name `$configResolver`). It does NOT inject or construct strategy/counter instances — that happens in Task 012, which has the category context needed for a join-safe count. The resolver returns a `ResolvedPaginationOptions` readonly DTO: `{ PageRequest $pageRequest, PaginationPresentation $presentation, PaginationStrategyKind $strategyKind, CountMode $countMode }`. It reads `config.strategy`/`config.countMode` and maps them to the enums (rejecting unknown/unsupported values — e.g. `cached`/`none` countModes — loudly via `InvalidPaginationConfigException`). Keep it fully unit-testable with a stub `ConfigResolverInterface`; no DB, no container.
- Size handling: if requested size not in `allowedPageSizes`, clamp to `defaultPageSize` (loudly, e.g. log/throw per standard) and never exceed `maxPageSize`. Default to `defaultPageSize` when absent.
- Sort handling: reject a requested sort not in `allowedSorts` with `InvalidPaginationConfigException`; default to `defaultSort`. Build the `Sort` (sort key + id tie-break).
- Depth: reject `page > maxPageDepth` with a dedicated out-of-range signal the controller maps to `410` (Task 018). Decide the mechanism explicitly and keep it stable across Tasks 015/018: either (a) a dedicated `PageDepthExceededException` (subclass of `MarkoException`) the controller catches and maps to 410, or (b) a boolean/typed result the controller inspects. Whichever is chosen, BOTH the main category controller (Task 018) and the fragment endpoint (Task 015) must use the SAME signal — name it here so downstream tasks reference one symbol. Recommended: `PageDepthExceededException::forDepth(int $requested, int $maxDepth)`.
- **Strategy/counter instantiation is NOT here — it is Task 012.** The resolver only resolves the `PaginationStrategyKind` + `CountMode` enums from config; Task 012 (which knows the category) constructs the concrete strategy and the join-safe counter. This keeps the resolver DB-free and avoids the impossible "build a join-safe counter without a category id" problem (`RepositoryQueryBuilder::count()` drops JOINs).
- **Presentation mapping:** `config.presentation` is a plain string (`numbered`|`load_more`|`infinite`); map it to the `PaginationPresentation` enum here, rejecting unknown values loudly with `InvalidPaginationConfigException`.
- **Combination validation:** `presentation = numbered` requires a random-access strategy (`offset`). `numbered + keyset` throws `InvalidPaginationConfigException` with a suggestion. `load_more`/`infinite` are valid with either strategy.

## Requirements (Test Descriptions)
- [x] `it builds a page request using the configured default size and sort`
- [x] `it clamps a requested size that is not in the allowed list`
- [x] `it never returns a size greater than the configured maximum`
- [x] `it rejects a sort that is not in the allowed list with a loud exception`
- [x] `it resolves the offset strategy kind and exact count mode from config`
- [x] `it resolves the keyset strategy kind when configured`
- [x] `it rejects an unsupported count mode with a loud exception`
- [x] `it rejects the numbered presentation combined with the keyset strategy`
- [x] `it throws PageDepthExceededException when the requested page exceeds the max depth`

## Acceptance Criteria
- Returns a `ResolvedPaginationOptions` DTO (`PageRequest` + `PaginationPresentation` + `PaginationStrategyKind` + `CountMode`); strategy/counter instances are built later in Task 012.
- Invalid size/sort/combo/countMode and excess depth all fail or clamp loudly; `PageDepthExceededException` is the shared depth signal reused by Tasks 015/018.
- Fully unit-tested with a stub `ConfigResolverInterface` (no DB).
- All requirements have passing tests.

## Implementation Notes

- Created `packages/catalog/src/Pagination/` with: `PaginationPresentation` (backed enum: numbered/load_more/infinite), `PaginationStrategyKind` (backed enum: offset/keyset), `CountMode` (backed enum: exact/estimated), `ResolvedPaginationOptions` (readonly DTO: PageRequest + int $page + PaginationPresentation + PaginationStrategyKind + CountMode), `PaginationOptionsResolver` (injected `ConfigResolverInterface $configResolver`, `resolve(?int $page, ?int $size, ?string $sort): ResolvedPaginationOptions`).
- Created `InvalidPaginationConfigException` and `PageDepthExceededException` extending `MarkoException` with `message`/`context`/`suggestion` static factories.
- Uses `PageRequest::first()` for all requests (offset position encoding handled by Task 012 via `int $page` in the DTO).
- Tested with an inline anonymous class stub implementing `ConfigResolverInterface`; no DB.
- PHPStan level 8: clean on new files; phpcs: clean; php-cs-fixer: clean.
