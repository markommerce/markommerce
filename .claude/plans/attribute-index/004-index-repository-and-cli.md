# Task 004: `IndexRepository` base + `IndexerRegistry` + unified `index:rebuild` command (core)

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
In `markommerce/indexer`, provide the shared persistence helper (bulk write / delete-by-ids /
truncate over a Postgres connection), an `IndexerRegistry` (name → `IndexerInterface`), and a SINGLE
unified `index:rebuild [name?] --chunk` CLI command that rebuilds a named index or all registered
indexes. This replaces per-index command classes — new indexers get CLI for free by registering a name.

## Context
- Pattern: `packages/catalog-price-index/src/Repositories/ProductPriceIndexRepository.php`
  (`upsertMany` raw multi-row `INSERT … ON CONFLICT … ::jsonb`) and
  `packages/catalog-price-index/src/Command/RebuildPriceIndexCommand.php` (CLI shape). STUDY both.
  The model is Magento's `bin/magento indexer:reindex [index...]` — one command, named indexers.
- Place in `packages/indexer/src/`.
- `IndexRepositoryInterface` + base (helper) injecting `Marko\Database\Connection\ConnectionInterface`:
  - `deleteByEntityIds(string $table, string $idColumn, array $ids): void`
  - `truncate(string $table): void`
  - bulk write helper `insertRows(string $table, list<string> $columns, list<array<mixed>> $rows): int`
    (multi-row parameterized `INSERT`; supports `?::jsonb` for JSON columns — per-column hint or the
    price-index `::jsonb` approach). Validate table/column identifiers against
    `/^[a-zA-Z_][a-zA-Z0-9_]*$/` (interpolated, not bound).
  - Keep BOTH an upsert helper (mirroring `upsertMany`, for the price per-product entry) and the
    delete+insert path (for the attribute EAV rows) so each indexer uses the fit-for-purpose one.
- **`IndexerRegistry`** (singleton): `register(string $name, IndexerInterface $indexer): void`,
  `get(string $name): IndexerInterface` (`@throws UnknownIndexException` — a new `MarkoException`
  with the list of known names in the suggestion), `names(): list<string>`, `all(): array<string,
  IndexerInterface>`. Each index package registers its indexer under a short name (`price`,
  `attribute`) in its module boot.
- **Unified command** `IndexRebuildCommand implements CommandInterface`,
  `#[Command(name: 'index:rebuild', description: 'Rebuild one or all product indexes')]`, injecting
  `IndexerRegistry`:
  - Optional positional argument `name`: rebuild only that index (registry `get($name)`). VERIFY
    Marko's `Input` exposes an optional positional argument (e.g. `getArgument('name')`); if it does
    NOT, fall back to an `--index=` option. Document which was used.
  - No `name` (or `--all`): rebuild EVERY registered indexer (`registry->all()`), summing counts.
  - `--chunk` option (default 500, `< 1 → 500` clamp like `RebuildPriceIndexCommand`).
  - Output a message containing each index name + its rebuilt row count, and a total.
  - Unknown name → `UnknownIndexException` (loud, lists known names).
- (Optional, nice-to-have, keep small) an `index:list` command printing registered index names — only
  if trivial; otherwise defer.

## Requirements (Test Descriptions)
- [x] `it inserts multiple rows in a single statement via the bulk helper`
- [x] `it deletes index rows by entity id`
- [x] `it truncates the index table`
- [x] `it rejects an invalid table or column identifier`
- [x] `it registers and retrieves an indexer by name`
- [x] `it throws UnknownIndexException for an unregistered index name`
- [x] `it rebuilds only the named index when a name is given`
- [x] `it rebuilds all registered indexes when no name is given`

## Acceptance Criteria
- Bulk write / delete-by-ids / truncate over `ConnectionInterface`; identifiers validated.
- `IndexerRegistry` resolves indexers by name; unknown name throws loudly with known names listed.
- `index:rebuild [name?] --chunk` rebuilds one or all registered indexers.

## Implementation Notes
- `IndexerInterface` defined in `src/Contracts/IndexerInterface.php` with `reindex(array $ids): int`,
  `reindexOne(int $id): int`, `rebuildAll(int $chunkSize = 500): int` (compatible with task 003).
- `IndexRepositoryInterface` + `IndexRepository` in `src/Contracts/` and `src/Repository/`.
  Identifier validation uses `/^[a-zA-Z_][a-zA-Z0-9_]*$/`; throws `\InvalidArgumentException`.
- `IndexerException` (base) + `UnknownIndexException` in `src/Exceptions/`, both extend `MarkoException`.
- `IndexerRegistry` in `src/Registry/` — not a true singleton, relies on DI container scope.
- `IndexRebuildCommand` uses `Input::getArgument(0)` for the optional positional `name` (Marko `Input`
  supports optional positional args via zero-based index after the command token).
- `FakeConnection` + `FakeStatement` in `tests/Support/` for unit testing without a real DB.
- All 8 requirements pass; 16 total tests in the package.
