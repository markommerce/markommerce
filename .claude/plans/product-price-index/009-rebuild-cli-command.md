# Task 009: catalog-price-index — `catalog:price-index:rebuild` CLI command

**Status**: completed
**Depends on**: 008
**Retry count**: 0

## Description
Add a CLI command `catalog:price-index:rebuild` that triggers a full index rebuild via `PriceIndexerInterface::rebuildAll()`, with an optional chunk-size option and clear, loud output (count rebuilt, duration). This is the v1 freshness mechanism — manual rebuild plus the explicit `reindexProduct(s)` API from T008 (no auto-observer in v1).

## Context
- New file `packages/catalog-price-index/src/Command/RebuildPriceIndexCommand.php`, using the project's `#[Command]` attribute pattern (auto-discovered by `CommandDiscovery` scanning `src/` — NO `commands` key in `module.php`). **Mirror `packages/config/src/Command/GenerateCommand.php` exactly:** `#[Command(name: 'catalog:price-index:rebuild', description: '…')]`, `implements CommandInterface`, method signature is `public function execute(Input $input, Output $output): int` (NOT `handle`), returning `0` on success. Use `$input` to read the option and `$output->writeLine(...)` for reporting.
- Command name `catalog:price-index:rebuild`; optional `--chunk=` option (default 500) passed to `rebuildAll($chunk)`. Read the option via the framework `Input` API (check `GenerateCommand`/`SetCommand` for the exact option-reading call); default to 500 when absent.
- Inject `PriceIndexerInterface`. On run: `$count = $this->priceIndexer->rebuildAll($chunk);` then `$output->writeLine(sprintf('Rebuilt %d price index entries.', $count));` and `return 0;`. The count comes from `rebuildAll`'s `int` return (T008). On failure let the loud exception propagate (don't swallow).
- Keep the command thin — no indexing logic in the command itself (that's all in `PriceIndexer`).

## Requirements (Test Descriptions)
- [x] `it is registered under the name catalog price index rebuild`
- [x] `it rebuilds the whole index when invoked`
- [x] `it passes the chunk size option through to the indexer`
- [x] `it defaults the chunk size when no option is given`
- [x] `it reports how many entries were rebuilt`

## Acceptance Criteria
- The command is discovered by `CommandDiscovery` (no manual registration).
- Invoking it calls `rebuildAll` with the resolved chunk size (asserted with a fake indexer).
- PHPStan level 8 clean; phpcs clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
