<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeOptionException;
use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\Attribute\Type\MultiselectType;
use Markommerce\Attribute\Type\SelectType;

function makeOptionDefinition(string $code = 'color', array $options = ['red', 'green', 'blue']): AttributeDefinitionInterface
{
    return new class ($code, $options) implements AttributeDefinitionInterface
    {
        public function __construct(
            private readonly string $attrCode,
            private readonly array $allowedOptions,
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
            return 'select';
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
            return ['options' => $this->allowedOptions];
        }
    };
}

it('accepts a select value that is a member of the allowed options', function (): void {
    $type = new SelectType();

    $result = $type->cast('red', makeOptionDefinition());

    expect($result)->toBe('red');
});

it('rejects a select value outside the allowed options', function (): void {
    $type = new SelectType();

    expect(fn () => $type->cast('purple', makeOptionDefinition()))
        ->toThrow(InvalidAttributeOptionException::class);
});

it('accepts a multiselect array whose members are all allowed', function (): void {
    $type = new MultiselectType();

    $result = $type->cast(['red', 'blue'], makeOptionDefinition());

    expect($result)->toBe(['red', 'blue']);
});

it('rejects a multiselect value that is not an array', function (): void {
    $type = new MultiselectType();

    expect(fn () => $type->cast('red', makeOptionDefinition()))
        ->toThrow(InvalidAttributeOptionException::class);
});

it('rejects a multiselect array containing an unknown member', function (): void {
    $type = new MultiselectType();

    expect(fn () => $type->cast(['red', 'purple'], makeOptionDefinition()))
        ->toThrow(InvalidAttributeOptionException::class);
});

it('serializes a multiselect value as a JSON array', function (): void {
    $type = new MultiselectType();

    $serialized = $type->serialize(['red', 'blue']);

    expect($serialized)->toBe('["red","blue"]');
});
