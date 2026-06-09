<?php

declare(strict_types=1);

use Markommerce\Criteria\Sort\NullsPlacement;
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

it('defaults to no nulls placement and no raw expression for a plain column sort field', function (): void {
    $field = new SortField(column: 'name');

    expect($field->nulls)->toBeNull()
        ->and($field->expression)->toBeNull();
});

it('accepts an explicit nulls last placement', function (): void {
    $field = new SortField(column: 'price', nulls: NullsPlacement::Last);

    expect($field->nulls)->toBe(NullsPlacement::Last);
});

it('accepts a raw expression instead of a plain column', function (): void {
    $field = new SortField(column: 'price', expression: "COALESCE(price_index->>'amount', price)");

    expect($field->expression)->toBe("COALESCE(price_index->>'amount', price)");
});

it('exposes the raw expression when one is set and falls back to the column otherwise', function (): void {
    $fieldWithExpr = new SortField(column: 'price', expression: 'COALESCE(price, 0)');
    $fieldWithoutExpr = new SortField(column: 'name');

    expect($fieldWithExpr->sortExpression())->toBe('COALESCE(price, 0)')
        ->and($fieldWithoutExpr->sortExpression())->toBe('name');
});

it('keeps existing two-argument SortField construction working unchanged', function (): void {
    // Positional two-argument form used throughout the existing codebase
    $field = new SortField('created_at', SortDirection::Descending);

    expect($field->column)->toBe('created_at')
        ->and($field->direction)->toBe(SortDirection::Descending)
        ->and($field->expression)->toBeNull()
        ->and($field->nulls)->toBeNull()
        ->and($field->sortExpression())->toBe('created_at');
});
