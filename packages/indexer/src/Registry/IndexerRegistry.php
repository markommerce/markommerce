<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Registry;

use Closure;
use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\Exceptions\UnknownIndexException;

class IndexerRegistry
{
    /** @var array<string, Closure(): IndexerInterface> */
    private array $resolvers = [];

    /** @var array<string, IndexerInterface> */
    private array $resolved = [];

    /**
     * Register an indexer under a name via a lazy resolver. The resolver is invoked only when the
     * index is actually rebuilt — so booting the app (or registering many indexers) never forces the
     * full indexer dependency graph to be constructed.
     *
     * @param Closure(): IndexerInterface $resolver
     */
    public function register(
        string $name,
        Closure $resolver,
    ): void {
        $this->resolvers[$name] = $resolver;
    }

    /**
     * @throws UnknownIndexException
     */
    public function get(string $name): IndexerInterface
    {
        if (!isset($this->resolvers[$name])) {
            throw UnknownIndexException::forName($name, $this->names());
        }

        return $this->resolved[$name] ??= ($this->resolvers[$name])();
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->resolvers);
    }
}
