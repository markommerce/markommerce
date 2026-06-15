<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\Attribute\Type\BoolType;
use Markommerce\Attribute\Type\DateType;
use Markommerce\Attribute\Type\DecimalType;
use Markommerce\Attribute\Type\FacetKind;
use Markommerce\Attribute\Type\IntType;
use Markommerce\Attribute\Type\TextType;

function makeAttributeDefinition(string $code = 'attr', array $config = []): AttributeDefinitionInterface
{
    return new class ($code, $config) implements AttributeDefinitionInterface
    {
        public function __construct(
            private readonly string $attrCode,
            private readonly array $attrConfig,
        ) {}

        public function code(): string
        {
            return $this->attrCode;
        }

        public function entityType(): string
        {
            return 'product';
        }

        public function type(): string
        {
            return 'text';
        }

        public function backing(): AttributeBacking
        {
            return AttributeBacking::Json;
        }

        public function isRequired(): bool
        {
            return false;
        }

        public function config(): array
        {
            return $this->attrConfig;
        }
    };
}

it('casts a valid string through the text type', function (): void {
    $type = new TextType();

    $result = $type->cast('hello world', makeAttributeDefinition());

    expect($result)->toBe('hello world');
});

it('rejects a non-string value in the text type', function (): void {
    $type = new TextType();

    expect(fn () => $type->cast(42, makeAttributeDefinition('color')))
        ->toThrow(InvalidAttributeValueException::class);
});

it('casts a valid integer through the int type', function (): void {
    $type = new IntType();

    $result = $type->cast(42, makeAttributeDefinition());

    expect($result)->toBe(42)
        ->and($type->code())->toBe('int')
        ->and($type->facetKind())->toBe(FacetKind::Term);
});

it('rejects a float value in the int type', function (): void {
    $type = new IntType();

    expect(fn () => $type->cast(3.14, makeAttributeDefinition('weight')))
        ->toThrow(InvalidAttributeValueException::class);
});

it('preserves decimal precision as a string without float rounding', function (): void {
    $type = new DecimalType();

    $result = $type->cast('0.10000000001', makeAttributeDefinition());

    expect($result)->toBe('0.10000000001')
        ->and($type->code())->toBe('decimal')
        ->and($type->facetKind())->toBe(FacetKind::Range);
});

it('rejects a non-numeric value in the decimal type', function (): void {
    $type = new DecimalType();

    expect(fn () => $type->cast('not-a-number', makeAttributeDefinition('price')))
        ->toThrow(InvalidAttributeValueException::class);
});

it('casts a true boolean through the bool type', function (): void {
    $type = new BoolType();
    $definition = makeAttributeDefinition('is_active');

    expect($type->cast(true, $definition))->toBeTrue()
        ->and($type->cast(false, $definition))->toBeFalse()
        ->and($type->code())->toBe('bool')
        ->and($type->facetKind())->toBe(FacetKind::Term);
});

it('rejects the string true in the bool type', function (): void {
    $type = new BoolType();

    expect(fn () => $type->cast('true', makeAttributeDefinition('is_active')))
        ->toThrow(InvalidAttributeValueException::class);
});

it('casts a valid ISO date string through the date type', function (): void {
    $type = new DateType();
    $definition = makeAttributeDefinition('published_at');

    $result = $type->cast('2024-06-15', $definition);

    expect($result)->toBeString()
        ->and($type->code())->toBe('date')
        ->and($type->facetKind())->toBe(FacetKind::Range);
});

it('normalizes an accepted date to a canonical ISO string', function (): void {
    $type = new DateType();
    $definition = makeAttributeDefinition('published_at');

    $fromString = $type->cast('2024-01-05', $definition);
    $fromDateTime = $type->cast(new DateTimeImmutable('2024-01-05'), $definition);

    expect($fromString)->toBe('2024-01-05')
        ->and($fromDateTime)->toBe('2024-01-05')
        ->and($fromString)->toBe($fromDateTime);
});

it('rejects an unparseable date value', function (): void {
    $type = new DateType();

    expect(fn () => $type->cast('not-a-date', makeAttributeDefinition('published_at')))
        ->toThrow(InvalidAttributeValueException::class);
});
