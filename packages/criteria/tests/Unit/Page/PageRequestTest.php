<?php

declare(strict_types=1);

use Markommerce\Criteria\Exceptions\InvalidPageSizeException;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Sort\SortField;

it('creates a first-page request with a null position', function (): void {
    $sort = new Sort(new SortField(column: 'id'));
    $request = PageRequest::first(size: 10, sort: $sort);

    expect($request->position)->toBeNull();
});

it('creates a positioned request carrying an opaque token', function (): void {
    $sort = new Sort(new SortField(column: 'id'));
    $request = PageRequest::at(size: 10, sort: $sort, position: 'cursor-abc123');

    expect($request->position)->toBe('cursor-abc123');
});

it('exposes the requested size and sort', function (): void {
    $sort = new Sort(new SortField(column: 'name'));
    $request = PageRequest::first(size: 25, sort: $sort);

    expect($request->size)->toBe(25)
        ->and($request->sort)->toBe($sort);
});

it('rejects a size of zero with a loud InvalidPageSizeException', function (): void {
    $sort = new Sort(new SortField(column: 'id'));

    expect(fn () => PageRequest::first(size: 0, sort: $sort))
        ->toThrow(InvalidPageSizeException::class);
});

it('rejects a negative size with a loud InvalidPageSizeException', function (): void {
    $sort = new Sort(new SortField(column: 'id'));

    expect(fn () => PageRequest::first(size: -5, sort: $sort))
        ->toThrow(InvalidPageSizeException::class);
});

it('the InvalidPageSizeException carries message, context and suggestion', function (): void {
    $exception = InvalidPageSizeException::forSize(0);

    expect($exception->getMessage())->not->toBeEmpty()
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
