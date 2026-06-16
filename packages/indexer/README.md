# markommerce/indexer

Shared indexer kernel for Markommerce — abstract lifecycle infrastructure reused by the price and attribute index packages. Provides `IndexerInterface`, `AbstractIndexer` (chunked rebuild/reindex), `ScopePassRunner` (save/restore scope context per signature), `CartesianServedScopesProvider`, `IndexRepository` (bulk insert/delete/truncate), `IndexerRegistry`, and the unified `index:rebuild` CLI command.

## Installation

```bash
composer require markommerce/indexer
```

## Quick Example

```php
<?php

declare(strict_types=1);

use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\AbstractIndexer;
use Markommerce\Indexer\ScopePassRunner;

// Implement AbstractIndexer to create a custom index
class MyIndexer extends AbstractIndexer
{
    protected function allIds(): iterable
    {
        return $this->repository->allIds();
    }

    protected function indexChunk(array $ids): int
    {
        // Build and persist index rows for $ids
        return count($ids);
    }
}

// Rebuild the full index in chunks (N+1-free)
$count = $myIndexer->rebuildAll(chunkSize: 500);

// Partial rebuild for specific IDs
$myIndexer->reindex(ids: [1, 2, 3]);
$myIndexer->reindexOne(id: 42);
```

```bash
# Rebuild all registered indexes
php marko index:rebuild

# Rebuild one named index
php marko index:rebuild attribute

# Custom chunk size
php marko index:rebuild --chunk=200
```

## Documentation

Full usage, API reference, and examples: [markommerce/indexer](https://markommerce.dev/docs/packages/indexer/)
