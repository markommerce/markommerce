<?php

declare(strict_types=1);

use Markommerce\Criteria\Exceptions\EmptySortException;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

it('builds a sort from an ordered list of sort fields', function (): void {
    $field = new SortField(column: 'name');
    $sort = new Sort($field);

    expect($sort->fields)->toHaveCount(1)
        ->and($sort->fields[0])->toBe($field);
});

it('preserves the order of sort fields', function (): void {
    $fieldA = new SortField(column: 'name');
    $fieldB = new SortField(column: 'created_at', direction: SortDirection::Descending);
    $sort = new Sort($fieldA, $fieldB);

    expect($sort->fields[0])->toBe($fieldA)
        ->and($sort->fields[1])->toBe($fieldB);
});

it('rejects a sort with no fields with a loud exception', function (): void {
    expect(fn () => new Sort())->toThrow(EmptySortException::class);
});
