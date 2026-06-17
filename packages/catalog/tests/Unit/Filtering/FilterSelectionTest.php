<?php

declare(strict_types=1);

use Markommerce\Catalog\Filtering\FilterSelection;

it('returns the selected values for a key and an empty list for an absent key', function (): void {
    $selection = new FilterSelection(['color' => ['red', 'blue'], 'size' => ['M']]);

    expect($selection->forKey('color'))->toBe(['red', 'blue'])
        ->and($selection->forKey('size'))->toBe(['M'])
        ->and($selection->forKey('brand'))->toBe([]);
});

it('reports whether the selection is empty', function (): void {
    $empty    = new FilterSelection([]);
    $nonEmpty = new FilterSelection(['color' => ['red']]);

    expect($empty->isEmpty())->toBeTrue()
        ->and($nonEmpty->isEmpty())->toBeFalse();
});

it('returns a copy without a given key for disjunctive faceting', function (): void {
    $selection = new FilterSelection(['color' => ['red', 'blue'], 'size' => ['M']]);
    $without   = $selection->without('color');

    expect($without->forKey('color'))->toBe([])
        ->and($without->forKey('size'))->toBe(['M'])
        ->and($without->keys())->toBe(['size']);

    // Original is unchanged (immutability)
    expect($selection->forKey('color'))->toBe(['red', 'blue']);
});
