<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Registry;

use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\Exceptions\UnknownIndexException;

class IndexerRegistry
{
    /** @var array<string, IndexerInterface> */
    private array $indexers = [];

    public function register(
        string $name,
        IndexerInterface $indexer,
    ): void
    {
        $this->indexers[$name] = $indexer;
    }

    /**
     * @throws UnknownIndexException
     */
    public function get(string $name): IndexerInterface
    {
        if (!isset($this->indexers[$name])) {
            throw UnknownIndexException::forName($name, $this->names());
        }

        return $this->indexers[$name];
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->indexers);
    }

    /** @return array<string, IndexerInterface> */
    public function all(): array
    {
        return $this->indexers;
    }
}
