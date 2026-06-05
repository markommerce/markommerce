<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Markommerce\Criteria\Page\Page;

// A minimal concrete entity for testing purposes
class TestEntity extends Entity {}

it('exposes its items collection and page size', function (): void {
    $items = new EntityCollection([new TestEntity()]);
    $page = new Page(items: $items, size: 10, nextPosition: null, previousPosition: null);

    expect($page->items)->toBe($items)
        ->and($page->size)->toBe(10);
});

it('reports hasNext true when a next position is present', function (): void {
    $items = new EntityCollection();
    $page = new Page(items: $items, size: 10, nextPosition: 'cursor-abc', previousPosition: null);

    expect($page->hasNext())->toBeTrue();
});

it('reports hasNext false when there is no next position', function (): void {
    $items = new EntityCollection();
    $page = new Page(items: $items, size: 10, nextPosition: null, previousPosition: null);

    expect($page->hasNext())->toBeFalse();
});

it('reports hasPrevious based on the previous position', function (): void {
    $items = new EntityCollection();
    $pageWithPrevious = new Page(items: $items, size: 10, nextPosition: null, previousPosition: 'cursor-xyz');
    $pageWithoutPrevious = new Page(items: $items, size: 10, nextPosition: null, previousPosition: null);

    expect($pageWithPrevious->hasPrevious())->toBeTrue()
        ->and($pageWithoutPrevious->hasPrevious())->toBeFalse();
});

it('exposes the opaque next and previous position tokens', function (): void {
    $items = new EntityCollection();
    $page = new Page(items: $items, size: 10, nextPosition: 'next-token', previousPosition: 'prev-token');

    expect($page->nextPosition)->toBe('next-token')
        ->and($page->previousPosition)->toBe('prev-token');
});
