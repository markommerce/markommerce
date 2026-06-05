<?php

declare(strict_types=1);

use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

it('creates a sort field with a column and default ascending direction', function (): void {
    $field = new SortField(column: 'name');

    expect($field->column)->toBe('name')
        ->and($field->direction)->toBe(SortDirection::Ascending);
});

it('creates a sort field with an explicit descending direction', function (): void {
    $field = new SortField(column: 'created_at', direction: SortDirection::Descending);

    expect($field->column)->toBe('created_at')
        ->and($field->direction)->toBe(SortDirection::Descending);
});
