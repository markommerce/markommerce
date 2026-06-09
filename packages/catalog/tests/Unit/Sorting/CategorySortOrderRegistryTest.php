<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Criteria\Sort\SortField;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class StubSortOrder implements CategorySortOrderInterface
{
    public function __construct(
        private readonly string $keyValue,
        private readonly string $labelValue = 'Label',
    ) {}

    public function key(): string
    {
        return $this->keyValue;
    }

    public function label(): string
    {
        return $this->labelValue;
    }

    public function supportsKeyset(): bool
    {
        return false;
    }

    public function prepareQuery(RepositoryQueryBuilder $repositoryQueryBuilder): void {}

    /** @return list<SortField> */
    public function sortFields(): array
    {
        return [];
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers a sort order and exposes it via all', function (): void {
    $registry  = new CategorySortOrderRegistry();
    $sortOrder = new StubSortOrder('name_asc');
    $registry->register($sortOrder);

    expect($registry->all())->toBe([$sortOrder]);
});

it('returns registered orders sorted by ascending priority', function (): void {
    $registry = new CategorySortOrderRegistry();
    $high     = new StubSortOrder('name_desc');
    $low      = new StubSortOrder('name_asc');

    $registry->register($high, 10);
    $registry->register($low, 1);

    $all = $registry->all();

    expect($all[0])->toBe($low)
        ->and($all[1])->toBe($high);
});

it('gets a registered order by its key', function (): void {
    $registry  = new CategorySortOrderRegistry();
    $sortOrder = new StubSortOrder('price_asc');
    $registry->register($sortOrder);

    expect($registry->get('price_asc'))->toBe($sortOrder);
});

it('returns null when getting an unknown key', function (): void {
    $registry = new CategorySortOrderRegistry();

    expect($registry->get('unknown_key'))->toBeNull();
});

it('reports whether a key is registered', function (): void {
    $registry  = new CategorySortOrderRegistry();
    $sortOrder = new StubSortOrder('name_asc');
    $registry->register($sortOrder);

    expect($registry->has('name_asc'))->toBeTrue()
        ->and($registry->has('missing'))->toBeFalse();
});

it('keeps the first registered order when two share the same key', function (): void {
    $registry = new CategorySortOrderRegistry();
    $first    = new StubSortOrder('name_asc', 'First Label');
    $second   = new StubSortOrder('name_asc', 'Second Label');

    $registry->register($first);
    $registry->register($second);

    expect($registry->get('name_asc'))->toBe($first)
        ->and($registry->all())->toHaveCount(1);
});
