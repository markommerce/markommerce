<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Exceptions\IncompatiblePositionException;
use Markommerce\Criteria\Exceptions\PageOutOfRangeException;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\KeysetPosition;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Criteria\Strategy\OffsetPage;
use Markommerce\Criteria\Strategy\OffsetPaginationStrategy;

// ---------------------------------------------------------------------------
// Fakes
// ---------------------------------------------------------------------------

class OffsetTestEntity extends Entity {}

class FakeOffsetQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<array{column: string, direction: string}> */
    public array $orderByCalls = [];
    public ?int $appliedLimit = null;
    public ?int $appliedOffset = null;

    public function __construct(
        private readonly int $fakeCount,
        private readonly int $fakeEntityCount,
    ) {
        // Skip parent constructor — no DB needed in unit tests.
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderByCalls[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->appliedLimit = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->appliedOffset = $offset;

        return $this;
    }

    public function count(?string $column = null): int
    {
        return $this->fakeCount;
    }

    /**
     * @return EntityCollection<Entity>
     */
    public function getEntities(): EntityCollection
    {
        $entities = [];
        for ($i = 0; $i < $this->fakeEntityCount; $i++) {
            $entities[] = new OffsetTestEntity();
        }

        /** @var EntityCollection<Entity> */
        return new EntityCollection($entities);
    }
}

class FakeRowCounter implements RowCounterInterface
{
    public function __construct(private readonly int $total) {}

    public function count(RepositoryQueryBuilder $query): int
    {
        return $this->total;
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeStrategy(int $total = 100): OffsetPaginationStrategy
{
    return new OffsetPaginationStrategy(
        positionCodec: new PositionCodec(),
        rowCounter: new FakeRowCounter($total),
    );
}

function makeQuery(int $fakeCount = 100, int $fakeEntityCount = 10): FakeOffsetQueryBuilder
{
    return new FakeOffsetQueryBuilder($fakeCount, $fakeEntityCount);
}

function makeOffsetPageRequest(int $size = 10, ?string $position = null): PageRequest
{
    $sort = new Sort(new SortField('name'));

    return $position === null
        ? PageRequest::first($size, $sort)
        : PageRequest::at($size, $sort, $position);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('returns the requested number of items for a full page', function (): void {
    // Query returns size+1 rows (11) to detect hasNext; strategy trims to size (10)
    $query = makeQuery(fakeEntityCount: 11);
    $strategy = makeStrategy(total: 100);
    $request = makeOffsetPageRequest(size: 10);

    $page = $strategy->paginate($query, $request);

    expect($page->items->count())->toBe(10)
        ->and($page->size)->toBe(10);
});

it('throws IncompatiblePositionException when given a keyset token', function (): void {
    $codec = new PositionCodec();
    $keysetToken = $codec->encode(new KeysetPosition(anchor: ['name' => 'foo'], id: 42));

    $query = makeQuery(fakeEntityCount: 10);
    $strategy = makeStrategy(total: 100);
    $request = makeOffsetPageRequest(size: 10, position: $keysetToken);

    expect(fn () => $strategy->paginate($query, $request))
        ->toThrow(IncompatiblePositionException::class);
});

it('throws PageOutOfRangeException for a page beyond the last', function (): void {
    $query = makeQuery(fakeEntityCount: 10);
    $strategy = makeStrategy(total: 20); // 2 pages of 10
    $request = makeOffsetPageRequest(size: 10);

    $page = $strategy->paginate($query, $request);

    assert($page instanceof OffsetPage);

    // Page 3 doesn't exist when totalPages = 2
    expect(fn () => $page->positionForPage(3))
        ->toThrow(PageOutOfRangeException::class);

    // Page 0 is also invalid
    expect(fn () => $page->positionForPage(0))
        ->toThrow(PageOutOfRangeException::class);
});

it('encodes an offset position for an arbitrary page via positionForPage', function (): void {
    $codec = new PositionCodec();
    $query = makeQuery(fakeEntityCount: 10);
    $strategy = makeStrategy(total: 50); // 5 pages of 10
    $request = makeOffsetPageRequest(size: 10);

    $page = $strategy->paginate($query, $request);

    assert($page instanceof OffsetPage);

    // positionForPage(3) should decode back to page=3
    $token = $page->positionForPage(3);
    $decoded = $codec->decode($token);

    assert($decoded instanceof OffsetPosition);

    expect($decoded)->toBeInstanceOf(OffsetPosition::class)
        ->and($decoded->page)->toBe(3);
});

it('derives current page total pages and total items from the counter', function (): void {
    $codec = new PositionCodec();
    $pageTwoToken = $codec->encode(new OffsetPosition(page: 2));

    $query = makeQuery(fakeEntityCount: 10);
    // Total is 25 items, size 10 → 3 pages; page 2 requested
    $strategy = makeStrategy(total: 25);
    $request = makeOffsetPageRequest(size: 10, position: $pageTwoToken);

    $page = $strategy->paginate($query, $request);

    assert($page instanceof OffsetPage);

    expect($page->currentPage())->toBe(2)
        ->and($page->totalPages())->toBe(3)
        ->and($page->totalItems())->toBe(25);
});

it('reports hasNext by fetching one extra row', function (): void {
    // When query returns exactly size rows (no extra), hasNext should be false.
    $queryNoNext = makeQuery(fakeEntityCount: 10);
    $strategyNoNext = makeStrategy(total: 10);
    $pageNoNext = $strategyNoNext->paginate($queryNoNext, makeOffsetPageRequest(size: 10));

    expect($pageNoNext->hasNext())->toBeFalse();

    // When query returns size+1 rows (extra row present), hasNext should be true.
    $queryWithNext = makeQuery(fakeEntityCount: 11);
    $strategyWithNext = makeStrategy(total: 100);
    $pageWithNext = $strategyWithNext->paginate($queryWithNext, makeOffsetPageRequest(size: 10));

    expect($pageWithNext->hasNext())->toBeTrue()
        ->and($pageWithNext->items->count())->toBe(10); // extra row trimmed
});

it('computes the offset from the requested page and size', function (): void {
    // Page 3 with size 10 → offset = (3-1)*10 = 20
    $codec = new PositionCodec();
    $pageThreeToken = $codec->encode(new OffsetPosition(page: 3));

    $query = makeQuery(fakeEntityCount: 10);
    $strategy = makeStrategy(total: 50);
    $request = makeOffsetPageRequest(size: 10, position: $pageThreeToken);

    $strategy->paginate($query, $request);

    expect($query->appliedOffset)->toBe(20)
        ->and($query->appliedLimit)->toBe(11); // size + 1
});

it('applies the sort fields and a deterministic id tie-break to the query', function (): void {
    $query = makeQuery(fakeEntityCount: 10);
    $strategy = makeStrategy(total: 30);
    $sort = new Sort(
        new SortField('name', SortDirection::Ascending),
        new SortField('created_at', SortDirection::Descending),
    );
    $request = PageRequest::first(size: 10, sort: $sort);

    $strategy->paginate($query, $request);

    expect($query->orderByCalls)->toBe([
        ['column' => 'name', 'direction' => 'ASC'],
        ['column' => 'created_at', 'direction' => 'DESC'],
        ['column' => 'id', 'direction' => 'ASC'],
    ]);
});
