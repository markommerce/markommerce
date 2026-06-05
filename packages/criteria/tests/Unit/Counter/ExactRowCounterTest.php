<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Counter\ExactRowCounter;

/**
 * Fake RepositoryQueryBuilder that returns a preset count without a DB connection.
 */
class FakeRepositoryQueryBuilder extends RepositoryQueryBuilder
{
    public function __construct(private readonly int $fakeCount)
    {
        // Skip parent constructor — no real DB needed in unit tests.
    }

    public function count(?string $column = null): int
    {
        return $this->fakeCount;
    }
}

it('returns the exact count from the query builder', function (): void {
    $query = new FakeRepositoryQueryBuilder(42);
    $counter = new ExactRowCounter();

    expect($counter->count($query))->toBe(42);
});
