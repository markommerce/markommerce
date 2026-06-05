<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\CursorValueExtractorInterface;
use Markommerce\Criteria\Contracts\RandomAccessPageInterface;
use Markommerce\Criteria\Exceptions\IncompatiblePositionException;
use Markommerce\Criteria\Exceptions\MissingCursorValueExtractorException;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\KeysetPosition;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

// ---------------------------------------------------------------------------
// Fakes
// ---------------------------------------------------------------------------

class KeysetTestEntity extends Entity
{
    public string $name = '';
    public int $id = 0;
}

class FakeKeysetQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<array{column: string, direction: string}> */
    public array $orderByCalls = [];
    public ?int $appliedLimit = null;
    /** @var list<array{expression: string, bindings: array<mixed>}> */
    public array $whereRawCalls = [];

    /** @var list<KeysetTestEntity> */
    private array $entities;

    /**
     * @param list<KeysetTestEntity> $entities
     */
    public function __construct(array $entities = [])
    {
        // Skip parent constructor — no DB needed in unit tests.
        $this->entities = $entities;
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

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function whereRaw(string $expression, array $bindings = []): static
    {
        $this->whereRawCalls[] = ['expression' => $expression, 'bindings' => $bindings];

        return $this;
    }

    /**
     * @return EntityCollection<Entity>
     */
    public function getEntities(): EntityCollection
    {
        /** @var EntityCollection<Entity> */
        return new EntityCollection($this->entities);
    }
}

class FakeCursorValueExtractor implements CursorValueExtractorInterface
{
    /**
     * @return array<string, scalar>
     */
    public function extract(object $entity, Sort $sort): array
    {
        assert($entity instanceof KeysetTestEntity);
        $result = [];
        foreach ($sort->fields as $field) {
            $result[$field->column] = $entity->{$field->column};
        }

        return $result;
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeKeysetStrategy(): KeysetPaginationStrategy
{
    return new KeysetPaginationStrategy(positionCodec: new PositionCodec());
}

/**
 * @param list<KeysetTestEntity> $entities
 */
function makeKeysetQuery(array $entities = []): FakeKeysetQueryBuilder
{
    return new FakeKeysetQueryBuilder($entities);
}

function makeKeysetRequest(int $size = 10, ?string $position = null): PageRequest
{
    $sort = new Sort(new SortField('name'));

    return $position === null
        ? PageRequest::first($size, $sort)
        : PageRequest::at($size, $sort, $position);
}

function makeKeysetRequestMultiSort(int $size = 10, ?string $position = null): PageRequest
{
    $sort = new Sort(
        new SortField('price', SortDirection::Ascending),
        new SortField('name', SortDirection::Descending),
    );

    return $position === null
        ? PageRequest::first($size, $sort)
        : PageRequest::at($size, $sort, $position);
}

/**
 * @return list<KeysetTestEntity>
 */
function makeEntities(int $count, int $startId = 1): array
{
    $entities = [];
    for ($i = 0; $i < $count; $i++) {
        $e = new KeysetTestEntity();
        $e->id = $startId + $i;
        $e->name = 'entity_' . ($startId + $i);
        $entities[] = $e;
    }

    return $entities;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('applies no seek predicate on the first page', function (): void {
    $query = makeKeysetQuery(makeEntities(5));
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5, position: null);

    $strategy->paginate($query, $request, new FakeCursorValueExtractor());

    expect($query->whereRawCalls)->toBeEmpty();
});

it('applies a seek predicate built from the decoded anchor on later pages', function (): void {
    $codec = new PositionCodec();
    $anchor = ['name' => 'entity_5'];
    $keysetPos = new KeysetPosition(anchor: $anchor, id: 5);
    $token = $codec->encode($keysetPos);

    $query = makeKeysetQuery(makeEntities(5, 6));
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5, position: $token);

    $strategy->paginate($query, $request, new FakeCursorValueExtractor());

    expect($query->whereRawCalls)->toHaveCount(1);
    $call = $query->whereRawCalls[0];
    expect($call['expression'])->toBe('(name, id) > (?, ?)')
        ->and($call['bindings'])->toBe(['entity_5', 5]);
});

it('orders by the sort fields with a deterministic id tie-break', function (): void {
    $query = makeKeysetQuery(makeEntities(3));
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequestMultiSort(size: 3);

    $strategy->paginate($query, $request, new FakeCursorValueExtractor());

    expect($query->orderByCalls)->toHaveCount(3)
        ->and($query->orderByCalls[0])->toBe(['column' => 'price', 'direction' => 'ASC'])
        ->and($query->orderByCalls[1])->toBe(['column' => 'name', 'direction' => 'DESC'])
        ->and($query->orderByCalls[2])->toBe(['column' => 'id', 'direction' => 'ASC']);
});

it('reports hasNext by fetching one extra row', function (): void {
    // Provide size+1 entities so hasNext is true; page should only contain size items
    $entities = makeEntities(6); // size = 5, fetched = 6
    $query = makeKeysetQuery($entities);
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5);

    $page = $strategy->paginate($query, $request, new FakeCursorValueExtractor());

    // limit should be size+1
    expect($query->appliedLimit)->toBe(6)
        // items should be trimmed to size
        ->and($page->items->count())->toBe(5)
        // hasNext should be true
        ->and($page->hasNext())->toBeTrue();
});

it('reports hasNext false when fewer than size+1 entities are returned', function (): void {
    $entities = makeEntities(3); // size = 5, fetched = 3 → no next
    $query = makeKeysetQuery($entities);
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5);

    $page = $strategy->paginate($query, $request, new FakeCursorValueExtractor());

    expect($page->hasNext())->toBeFalse()
        ->and($page->items->count())->toBe(3);
});

it('encodes the next position from the boundary entity values via the extractor', function (): void {
    // 6 entities for size=5 → hasNext = true; last entity in the page is entity id=5
    $entities = makeEntities(6); // ids 1..6, names entity_1..entity_6
    $query = makeKeysetQuery($entities);
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5);

    $page = $strategy->paginate($query, $request, new FakeCursorValueExtractor());

    expect($page->nextPosition)->not->toBeNull();

    // Decode the next position and verify it encodes the last returned entity (id=5, name=entity_5)
    $codec = new PositionCodec();
    assert($page->nextPosition !== null);
    $decoded = $codec->decode($page->nextPosition);

    assert($decoded instanceof KeysetPosition);
    expect($decoded)->toBeInstanceOf(KeysetPosition::class)
        ->and($decoded->id)->toBe(5)
        ->and($decoded->anchor)->toBe(['name' => 'entity_5']);
});

it('throws IncompatiblePositionException when given an offset token', function (): void {
    $codec = new PositionCodec();
    $offsetPosition = new OffsetPosition(page: 2);
    $token = $codec->encode($offsetPosition);

    $query = makeKeysetQuery();
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5, position: $token);

    expect(fn () => $strategy->paginate($query, $request, new FakeCursorValueExtractor()))
        ->toThrow(IncompatiblePositionException::class);
});

it('its page does not implement the random-access interface', function (): void {
    $query = makeKeysetQuery(makeEntities(3));
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5);

    $page = $strategy->paginate($query, $request, new FakeCursorValueExtractor());

    expect($page)->not->toBeInstanceOf(RandomAccessPageInterface::class);
});

it('throws MissingCursorValueExtractorException when extractor is null and there is a next page', function (): void {
    // 6 entities for size=5 → hasNext = true but no extractor supplied
    $entities = makeEntities(6);
    $query = makeKeysetQuery($entities);
    $strategy = makeKeysetStrategy();
    $request = makeKeysetRequest(size: 5);

    expect(fn () => $strategy->paginate($query, $request, null))
        ->toThrow(MissingCursorValueExtractorException::class);
});
