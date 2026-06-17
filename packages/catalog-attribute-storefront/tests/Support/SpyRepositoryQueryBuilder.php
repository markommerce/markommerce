<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\Tests\Support;

use Marko\Database\Repository\RepositoryQueryBuilder;

/**
 * A spy subclass of RepositoryQueryBuilder that captures whereRaw calls without needing a real DB.
 */
class SpyRepositoryQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<array{sql: string, bindings: list<mixed>}> */
    public array $whereRawCalls = [];

    public function __construct()
    {
        // Skip parent constructor — no real DB needed in unit tests.
    }

    /**
     * @param array<mixed> $bindings
     */
    public function whereRaw(
        string $expression,
        array $bindings = [],
    ): static {
        $this->whereRawCalls[] = ['sql' => $expression, 'bindings' => array_values($bindings)];

        return $this;
    }
}
