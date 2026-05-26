<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\ExtensionAttribute;
use Markommerce\Layout\Exceptions\DuplicateExtensionException;
use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

class EDT_ReviewStarsExtension implements ExtensionAttribute
{
    public function __construct(public readonly float $stars) {}
}

class EDT_PriceExtension implements ExtensionAttribute
{
    public function __construct(public readonly float $price) {}
}

abstract readonly class EDT_ProductCardData extends ExtensibleData
{
    public function __construct(
        public int $id,
        public string $name,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}

readonly class EDT_ConcreteProductCardData extends EDT_ProductCardData {}

// ---------------------------------------------------------------------------
// ExtensionBag tests
// ---------------------------------------------------------------------------

it('stores an extension and retrieves it by class', function (): void {
    $extension = new EDT_ReviewStarsExtension(stars: 4.5);
    $bag = new ExtensionBag();
    $bag = $bag->with($extension);

    $result = $bag->get(EDT_ReviewStarsExtension::class);

    expect($result)->toBeInstanceOf(EDT_ReviewStarsExtension::class)
        ->and($result->stars)->toBe(4.5);
});

it('returns null when an extension class is not present in the bag', function (): void {
    $bag = new ExtensionBag();

    $result = $bag->get(EDT_ReviewStarsExtension::class);

    expect($result)->toBeNull();
});

it('returns a new bag when an extension is added preserving immutability', function (): void {
    $extension = new EDT_ReviewStarsExtension(stars: 4.5);
    $original = new ExtensionBag();
    $newBag = $original->with($extension);

    expect($newBag)->not->toBe($original)
        ->and($original->get(EDT_ReviewStarsExtension::class))->toBeNull()
        ->and($newBag->get(EDT_ReviewStarsExtension::class))->toBeInstanceOf(EDT_ReviewStarsExtension::class);
});

it('rejects two extensions of the same class in one bag', function (): void {
    $first = new EDT_ReviewStarsExtension(stars: 4.5);
    $second = new EDT_ReviewStarsExtension(stars: 3.0);
    $bag = (new ExtensionBag())->with($first);

    expect(fn () => $bag->with($second))
        ->toThrow(DuplicateExtensionException::class);
});

it('exposes an empty extension bag by default on ExtensibleData', function (): void {
    $data = new EDT_ConcreteProductCardData(id: 1, name: 'Test Product');

    expect($data->extensions)->toBeInstanceOf(ExtensionBag::class)
        ->and($data->extensions->get(EDT_ReviewStarsExtension::class))->toBeNull();
});

it('returns a new data object with the extension when withExtension is called', function (): void {
    $data = new EDT_ConcreteProductCardData(id: 1, name: 'Test Product');
    $extension = new EDT_ReviewStarsExtension(stars: 4.5);

    $augmented = $data->withExtension($extension);

    expect($augmented)->toBeInstanceOf(EDT_ConcreteProductCardData::class)
        ->and($augmented)->not->toBe($data)
        ->and($augmented->extensions->get(EDT_ReviewStarsExtension::class))->toBeInstanceOf(
            EDT_ReviewStarsExtension::class,
        )
        ->and($augmented->extensions->get(EDT_ReviewStarsExtension::class)->stars)->toBe(4.5);
});

it('keeps original core fields intact after withExtension', function (): void {
    $data = new EDT_ConcreteProductCardData(id: 42, name: 'My Product');
    $extension = new EDT_ReviewStarsExtension(stars: 5.0);

    $augmented = $data->withExtension($extension);

    expect($augmented->id)->toBe(42)
        ->and($augmented->name)->toBe('My Product')
        ->and($data->extensions->get(EDT_ReviewStarsExtension::class))->toBeNull();
});
