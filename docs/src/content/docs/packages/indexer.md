---
title: markommerce/indexer
description: Shared indexer kernel for Markommerce — abstract lifecycle infrastructure reused by the price and attribute index packages.
---

Shared indexer kernel for Markommerce. `markommerce/indexer` provides the abstract lifecycle infrastructure that every index package builds on: a chunked rebuild/reindex contract (`IndexerInterface` and `AbstractIndexer`), a scope-context save/restore runner (`ScopePassRunner`), a cartesian served-scopes provider, a low-level bulk SQL helper (`IndexRepository`), a name-to-indexer registry (`IndexerRegistry`), and the unified `index:rebuild` CLI command. It abstracts the indexer lifecycle and utilities --- not the row shape, which each consumer defines in its own entity.

## Installation

```bash
composer require markommerce/indexer
```

The package is a `marko-module` and registers its bindings automatically via `module.php`.

## Usage

### Implementing a custom indexer

Extend `AbstractIndexer` and implement the two protected methods. `AbstractIndexer` handles chunking for both full rebuilds and partial reindexes:

```php
<?php

declare(strict_types=1);

use Markommerce\Indexer\AbstractIndexer;

class MyIndexer extends AbstractIndexer
{
    public function __construct(
        private MyProductRepository $myProductRepository,
        private MyIndexRepository $myIndexRepository,
    ) {}

    protected function allIds(): iterable
    {
        $rows = $this->myProductRepository->query()->selectRaw('id')->get();

        return array_map(fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param list<int> $ids
     */
    protected function indexChunk(array $ids): int
    {
        // Load entities, build rows, persist, return row count
        return count($ids);
    }
}
```

`AbstractIndexer::rebuildAll()` calls `allIds()` once, then feeds chunks of `$chunkSize` to `indexChunk()`. `reindex()` and `reindexOne()` call `indexChunk()` directly.

### Registering with the IndexerRegistry

Register the indexer by name in your module's `boot` closure. The name is the string used with the `index:rebuild` CLI command:

```php
<?php

declare(strict_types=1);

use Markommerce\Indexer\Registry\IndexerRegistry;

// In module.php boot closure:
return [
    // ...
    'boot' => function (
        IndexerRegistry $indexerRegistry,
        MyIndexer $myIndexer,
    ): void {
        $indexerRegistry->register('my-index', $myIndexer);
    },
];
```

### Rebuilding from the CLI

Once an indexer is registered, the unified `index:rebuild` command can rebuild it by name or rebuild all registered indexes in one pass:

```bash
# Rebuild a specific named index
php marko index:rebuild my-index

# Rebuild all registered indexes
php marko index:rebuild

# Custom chunk size (default: 500)
php marko index:rebuild --chunk=200
php marko index:rebuild my-index --chunk=200
```

If an unknown name is given, `UnknownIndexException` is thrown with the list of known names.

### Using ScopePassRunner

`ScopePassRunner` runs a callback once for the base (global) pass and once per `ScopeSignature`. It saves the full ambient `ScopeContext` state before and restores it after (including on exception):

```php
<?php

declare(strict_types=1);

use Markommerce\Indexer\ScopePassRunner;
use Markommerce\Scope\Signature\ScopeSignature;

class ScopedIndexer
{
    public function __construct(
        private ScopePassRunner $scopePassRunner,
    ) {}

    public function run(array $signatures): void
    {
        $this->scopePassRunner->each(
            $signatures,
            function (?ScopeSignature $signature): void {
                if ($signature === null) {
                    // Base pass — scope context cleared to global defaults
                } else {
                    // Scoped pass — $signature axes are set in ScopeContext
                    $market = $signature->get('market');
                }
            },
        );
        // ScopeContext restored to its original state here
    }
}
```

### Using CartesianServedScopesProvider

`CartesianServedScopesProvider` returns `ScopeSignature` instances for every non-default combination across the given scope axes. It caps at 1,024 signatures and fires a `E_USER_WARNING` if the cartesian product exceeds that limit:

```php
<?php

declare(strict_types=1);

use Markommerce\Indexer\ServedScopes\ServedScopesProviderInterface;

class MyIndexer
{
    public function __construct(
        private ServedScopesProviderInterface $servedScopesProvider,
    ) {}

    public function signatures(): array
    {
        // All non-default combinations of locale × market
        return $this->servedScopesProvider->signatures(['locale', 'market']);
    }
}
```

`CartesianServedScopesProvider` is the default binding for `ServedScopesProviderInterface`. It reads registered axes from `ScopeRegistryInterface` and skips any axis that has no non-default paths.

### Using IndexRepository

`IndexRepository` provides three low-level operations against any index table. Table and column names are validated against a strict identifier pattern (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`); an `InvalidArgumentException` is thrown on invalid input:

```php
<?php

declare(strict_types=1);

use Markommerce\Indexer\Contracts\IndexRepositoryInterface;

class MyIndexWriter
{
    public function __construct(
        private IndexRepositoryInterface $indexRepository,
    ) {}

    public function write(array $productIds, array $rows): void
    {
        // Delete existing rows for this chunk
        $this->indexRepository->deleteByEntityIds(
            table: 'my_index_table',
            idColumn: 'product_id',
            ids: $productIds,
        );

        // Batch-insert new rows in a single SQL statement
        $this->indexRepository->insertRows(
            table: 'my_index_table',
            columns: ['product_id', 'value'],
            rows: $rows,
        );
    }

    public function truncate(): void
    {
        $this->indexRepository->truncate('my_index_table');
    }
}
```

## API Reference

### `IndexerInterface`

| Method | Return type | Description |
|---|---|---|
| `reindex(array $ids)` | `int` | Reindex the given list of entity IDs. Returns the count of written rows. |
| `reindexOne(int $id)` | `int` | Reindex a single entity. Returns the count of written rows. |
| `rebuildAll(int $chunkSize = 500)` | `int` | Full rebuild: load all entity IDs, process in chunks. Returns total row count. |

### `AbstractIndexer`

Skeletal implementation of `IndexerInterface`. Subclasses implement:

| Method | Description |
|---|---|
| `allIds(): iterable<int>` | Return all entity IDs for a full rebuild. |
| `indexChunk(list<int> $ids): int` | Produce and persist index rows for a chunk of IDs. Returns the count of written rows. |

### `ScopePassRunner`

| Method | Description |
|---|---|
| `each(list<ScopeSignature> $signatures, callable $fn): void` | Run `$fn(null)` for the base pass, then `$fn($signature)` for each signature. Saves and restores ambient `ScopeContext` state. Throws `UnknownAxisException` or `ScopeContextException` on invalid axis/path. |

### `ServedScopesProviderInterface`

| Method | Return type | Description |
|---|---|
| `signatures(list<string> $axes): list<ScopeSignature>` | Return non-default `ScopeSignature` instances for the given axis subset. |

`CartesianServedScopesProvider` is the default binding. Cap: `CartesianServedScopesProvider::MAX_SIGNATURES = 1024`.

### `IndexRepositoryInterface`

| Method | Return type | Description |
|---|---|
| `insertRows(string $table, list<string> $columns, list<array<mixed>> $rows): int` | Batch-insert rows in a single SQL statement. Returns the count of inserted rows. |
| `deleteByEntityIds(string $table, string $idColumn, list<int> $ids): void` | Delete rows matching any of the given entity IDs. |
| `truncate(string $table): void` | Truncate the entire index table. |

### `IndexerRegistry`

| Method | Return type | Description |
|---|---|
| `register(string $name, IndexerInterface $indexer): void` | Register an indexer under a name. |
| `get(string $name): IndexerInterface` | Retrieve a registered indexer. Throws `UnknownIndexException` if not found. |
| `names(): list<string>` | Return all registered names. |
| `all(): array<string, IndexerInterface>` | Return the full name-to-indexer map. |

### CLI Command

| Command | Arguments | Options | Description |
|---|---|---|---|
| `index:rebuild` | `[name?]` | `--chunk=N` | Rebuild one named index or all registered indexes. Default chunk size: `500`. |

### Exceptions

| Exception | When thrown |
|---|---|
| `UnknownIndexException` | `IndexerRegistry::get()` called with an unregistered name. Message includes all known names. |

## Module Bindings

`module.php` registers the following default bindings:

| Interface / Class | Default Implementation |
|---|---|
| `IndexRepositoryInterface` | `IndexRepository` |
| `ServedScopesProviderInterface` | `CartesianServedScopesProvider` |
| `IndexerRegistry` | Singleton |

## Related Packages

- [markommerce/catalog-price-index](/docs/packages/catalog-price-index/) --- price index built on this kernel; registered as `price`
- [markommerce/catalog-attribute-index](/docs/packages/catalog-attribute-index/) --- attribute EAV index built on this kernel; registered as `attribute`
- [markommerce/scope](/docs/packages/scope/) --- `ScopeContext`, `ScopeSignature`, `ScopeRegistryInterface` consumed by `ScopePassRunner` and `CartesianServedScopesProvider`
