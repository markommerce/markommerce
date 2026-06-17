<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\FacetKind;

function makeAttributeType(string $code): AttributeTypeInterface
{
    return new class ($code) implements AttributeTypeInterface
    {
        public function __construct(private readonly string $typeCode) {}

        public function code(): string
        {
            return $this->typeCode;
        }

        /**
         * @throws InvalidAttributeValueException
         */
        public function cast(
            mixed $raw,
            AttributeDefinitionInterface $definition,
        ): mixed
        {
            return $raw;
        }

        public function serialize(mixed $value): mixed
        {
            return $value;
        }

        public function deserialize(mixed $stored): mixed
        {
            return $stored;
        }

        public function facetKind(): FacetKind
        {
            return FacetKind::None;
        }
    };
}

it('registers a type and retrieves it by its code', function (): void {
    $registry = new AttributeTypeRegistry();
    $type = makeAttributeType('text');

    $registry->register($type);

    expect($registry->get('text'))->toBe($type);
});

it('reports whether a type code is registered', function (): void {
    $registry = new AttributeTypeRegistry();
    $type = makeAttributeType('integer');

    expect($registry->has('integer'))->toBeFalse();

    $registry->register($type);

    expect($registry->has('integer'))->toBeTrue();
});

it('overrides a previously registered type with the same code', function (): void {
    $registry = new AttributeTypeRegistry();
    $first = makeAttributeType('text');
    $second = makeAttributeType('text');

    $registry->register($first);
    $registry->register($second);

    expect($registry->get('text'))->toBe($second)
        ->and($registry->get('text'))->not->toBe($first);
});

it('throws UnknownAttributeTypeException when getting an unregistered code', function (): void {
    $registry = new AttributeTypeRegistry();

    expect(fn () => $registry->get('nonexistent'))->toThrow(UnknownAttributeTypeException::class);
});

it('returns all registered types keyed by code', function (): void {
    $registry = new AttributeTypeRegistry();
    $text = makeAttributeType('text');
    $integer = makeAttributeType('integer');

    $registry->register($text);
    $registry->register($integer);

    $all = $registry->all();

    expect($all)->toHaveCount(2)
        ->and($all)->toHaveKey('text')
        ->and($all)->toHaveKey('integer')
        ->and($all['text'])->toBe($text)
        ->and($all['integer'])->toBe($integer);
});
