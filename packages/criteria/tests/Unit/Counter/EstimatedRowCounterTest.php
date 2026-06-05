<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Counter\CountEstimateSourceInterface;
use Markommerce\Criteria\Counter\EstimatedRowCounter;
use Markommerce\Criteria\Counter\QueryFilterDetectorInterface;

/**
 * Fake RepositoryQueryBuilder — no DB connection needed.
 */
class FakeQueryBuilderForEstimate extends RepositoryQueryBuilder
{
    public function __construct(private readonly int $fakeCount)
    {
        // Skip parent constructor.
    }

    public function count(?string $column = null): int
    {
        return $this->fakeCount;
    }
}

/**
 * Fake exact row counter.
 */
class FakeExactCounter implements RowCounterInterface
{
    public function __construct(private readonly int $fakeCount) {}

    public function count(RepositoryQueryBuilder $query): int
    {
        return $this->fakeCount;
    }
}

/**
 * Fake estimate source that returns a preset value or null.
 */
class FakeCountEstimateSource implements CountEstimateSourceInterface
{
    public function __construct(private readonly ?int $estimate) {}

    public function estimate(RepositoryQueryBuilder $query): ?int
    {
        return $this->estimate;
    }
}

/**
 * Fake filter detector.
 */
class FakeQueryFilterDetector implements QueryFilterDetectorInterface
{
    public function __construct(private readonly bool $isFiltered) {}

    public function isFiltered(RepositoryQueryBuilder $query): bool
    {
        return $this->isFiltered;
    }
}

it('returns an estimated count when a planner estimate is available', function (): void {
    $query = new FakeQueryBuilderForEstimate(fakeCount: 999);
    $exactCounter = new FakeExactCounter(fakeCount: 999);
    $estimateSource = new FakeCountEstimateSource(estimate: 1500);
    $filterDetector = new FakeQueryFilterDetector(isFiltered: false);

    $counter = new EstimatedRowCounter(
        rowCounter: $exactCounter,
        countEstimateSource: $estimateSource,
        queryFilterDetector: $filterDetector,
    );

    expect($counter->count($query))->toBe(1500);
});

it('falls back to the exact count when no estimate is available', function (): void {
    $query = new FakeQueryBuilderForEstimate(fakeCount: 55);
    $exactCounter = new FakeExactCounter(fakeCount: 55);
    $estimateSource = new FakeCountEstimateSource(estimate: null);
    $filterDetector = new FakeQueryFilterDetector(isFiltered: false);

    $counter = new EstimatedRowCounter(
        rowCounter: $exactCounter,
        countEstimateSource: $estimateSource,
        queryFilterDetector: $filterDetector,
    );

    expect($counter->count($query))->toBe(55);
});

it('falls back to the exact count when the query is filtered', function (): void {
    $query = new FakeQueryBuilderForEstimate(fakeCount: 10);
    $exactCounter = new FakeExactCounter(fakeCount: 10);
    $estimateSource = new FakeCountEstimateSource(estimate: 9999);
    $filterDetector = new FakeQueryFilterDetector(isFiltered: true);

    $counter = new EstimatedRowCounter(
        rowCounter: $exactCounter,
        countEstimateSource: $estimateSource,
        queryFilterDetector: $filterDetector,
    );

    expect($counter->count($query))->toBe(10);
});
