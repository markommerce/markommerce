<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\Operation;
use Markommerce\Layout\Operation\Append;
use Markommerce\Layout\Operation\InsertAfter;
use Markommerce\Layout\Operation\InsertBefore;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\Prepend;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Operation\Replace;
use Markommerce\Layout\Operation\ReplaceProps;
use Markommerce\Layout\Operation\WrapWith;
use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Place;

it('builds an InsertBefore operation with an anchor name and placement', function (): void {
    $place = new Place('SomeComponent', 'some.name', [], []);
    $op = new InsertBefore('product_grid', $place);

    expect($op->anchorName)->toBe('product_grid');
    expect($op->placement)->toBe($place);
});

it('builds an InsertAfter operation with an anchor name and placement', function (): void {
    $place = new Place('SomeComponent', 'some.name', [], []);
    $op = new InsertAfter('product_grid', $place);

    expect($op->anchorName)->toBe('product_grid');
    expect($op->placement)->toBe($place);
});

it('builds an Append operation with a slot path and placement', function (): void {
    $place = new Place('SomeComponent', 'some.name', [], []);
    $op = new Append('content.product_grid', $place);

    expect($op->slotPath)->toBe('content.product_grid');
    expect($op->placement)->toBe($place);
});

it('builds a Prepend operation with a slot path and placement', function (): void {
    $place = new Place('SomeComponent', 'some.name', [], []);
    $op = new Prepend('content.product_grid', $place);

    expect($op->slotPath)->toBe('content.product_grid');
    expect($op->placement)->toBe($place);
});

it('builds a Remove operation with a placement name', function (): void {
    $op = new Remove('product_grid');

    expect($op->name)->toBe('product_grid');
});

it('builds a Replace operation with a name and replacement placement', function (): void {
    $place = new Place('NewComponent', 'new.name', [], []);
    $op = new Replace('product_grid', $place);

    expect($op->name)->toBe('product_grid');
    expect($op->placement)->toBe($place);
});

it('builds a MergeProps operation with a name and prop map', function (): void {
    $op = new MergeProps('product_card', ['sku' => 'ABC123', 'color' => 'blue']);

    expect($op->name)->toBe('product_card');
    expect($op->props)->toBe(['sku' => 'ABC123', 'color' => 'blue']);
});

it('builds a ReplaceProps operation with a name and prop map', function (): void {
    $op = new ReplaceProps('product_card', ['sku' => 'XYZ789']);

    expect($op->name)->toBe('product_card');
    expect($op->props)->toBe(['sku' => 'XYZ789']);
});

it('builds a WrapWith operation with a name and decorator class', function (): void {
    $op = new WrapWith('product_card', 'Some\\Decorator\\Class');

    expect($op->name)->toBe('product_card');
    expect($op->decorator)->toBe('Some\\Decorator\\Class');
});

it('marks every operation with the common operation interface', function (): void {
    $place = new Place('SomeComponent', 'some.name', [], []);

    expect(new InsertBefore('anchor', $place))->toBeInstanceOf(Operation::class);
    expect(new InsertAfter('anchor', $place))->toBeInstanceOf(Operation::class);
    expect(new Append('slot.path', $place))->toBeInstanceOf(Operation::class);
    expect(new Prepend('slot.path', $place))->toBeInstanceOf(Operation::class);
    expect(new Remove('name'))->toBeInstanceOf(Operation::class);
    expect(new Replace('name', $place))->toBeInstanceOf(Operation::class);
    expect(new MergeProps('name', ['key' => 'val']))->toBeInstanceOf(Operation::class);
    expect(new ReplaceProps('name', ['key' => 'val']))->toBeInstanceOf(Operation::class);
    expect(new WrapWith('name', 'Some\\Decorator'))->toBeInstanceOf(Operation::class);
});

it('builds a LayoutExtension with a handle, operations and a default priority of zero', function (): void {
    $place = new Place('SomeComponent', 'some.name', [], []);
    $op = new InsertBefore('anchor', $place);
    $extension = new LayoutExtension('catalog_product_view', [$op]);

    expect($extension->handle)->toBe('catalog_product_view');
    expect($extension->operations)->toBe([$op]);
    expect($extension->priority)->toBe(0);
});

it('builds a LayoutExtension with an explicit priority', function (): void {
    $place = new Place('SomeComponent', 'some.name', [], []);
    $op = new InsertBefore('anchor', $place);
    $extension = new LayoutExtension('catalog_product_view', [$op], 10);

    expect($extension->priority)->toBe(10);
});
