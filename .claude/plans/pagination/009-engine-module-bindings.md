# Task 009: Engine module.php bindings

**Status**: complete
**Depends on**: 006, 007, 008
**Retry count**: 0

## Description
Wire the `packages/criteria` module bindings so the engine has safe generic defaults that downstream packages can override via Marko Preferences: keyset strategy and exact counter.

## Context
- File: `packages/criteria/module.php`.
- Bindings: `PaginationStrategyInterface::class => KeysetPaginationStrategy::class` (safe generic default for unknown consumers), `RowCounterInterface::class => ExactRowCounter::class`.
- Catalog does NOT rely on these bindings for the storefront — it selects offset+exact by config (Task 011) — but the engine must still resolve sensibly out of the box.
- For `it resolves a working strategy from the container` to pass, both default-bound classes must be container-constructible with NO per-call arguments in their constructors. The `CursorValueExtractor` (Task 008) and `RowCounterInterface` (for offset) are passed at `paginate()` time / by the caller, NOT injected positionally in a way the container can't fill — verify the strategy constructors only take container-resolvable deps (e.g. `PositionCodec`, which is dependency-free). If `KeysetPaginationStrategy` needs the extractor at construction time, the default binding would fail to resolve — keep the extractor a `paginate()` parameter (or a setter) so the bare strategy is resolvable.
- Follow the minimal `module.php` shape from `.claude/architecture.md`.

## Requirements (Test Descriptions)
- [x] `it binds the pagination strategy interface to the keyset strategy by default`
- [x] `it binds the row counter interface to the exact counter by default`
- [x] `it resolves a working strategy from the container with default bindings`

## Acceptance Criteria
- Module bindings load and resolve through the Marko container in a feature test.
- All requirements have passing tests.
- No decrease in coverage.

## Implementation Notes
- Wired `PaginationStrategyInterface => KeysetPaginationStrategy` and `RowCounterInterface => ExactRowCounter` in `packages/criteria/module.php`.
- Feature test in `packages/criteria/tests/Feature/ModuleBindingsTest.php` boots a minimal `Container` from the module array and asserts both bindings exist and the strategy resolves to a live `KeysetPaginationStrategy` instance.
- Both implementations are zero-extra-dep: `ExactRowCounter` has no constructor, `KeysetPaginationStrategy` takes only `PositionCodec` (no constructor args), so the container resolves both without manual wiring.
- PHPStan level 8: no errors. 52/52 tests pass.
