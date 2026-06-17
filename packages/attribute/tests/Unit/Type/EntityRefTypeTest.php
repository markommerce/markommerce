<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\Attribute\Type\EntityRefType;

function makeEntityRefDefinition(array $config = []): AttributeDefinitionInterface
{
    return new class ($config) implements AttributeDefinitionInterface
    {
        public function __construct(
            private readonly array $attrConfig,
        ) {}

        public function code(): string
        {
            return 'product_ref';
        }

        public function entityType(): string
        {
            return 'product';
        }

        public function type(): string
        {
            return 'entityRef';
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

it('accepts a positive integer reference when a target entity type is configured', function (): void {
    $type = new EntityRefType();
    $definition = makeEntityRefDefinition(['targetEntityType' => 'product']);

    $result = $type->cast(42, $definition);

    expect($result)->toBe(42);
});

it('rejects a non-integer reference value', function (): void {
    $type = new EntityRefType();
    $definition = makeEntityRefDefinition(['targetEntityType' => 'product']);

    expect(fn () => $type->cast('not-an-int', $definition))
        ->toThrow(InvalidAttributeValueException::class);
});

it('rejects a zero or negative reference value', function (): void {
    $type = new EntityRefType();
    $definition = makeEntityRefDefinition(['targetEntityType' => 'product']);

    expect(fn () => $type->cast(0, $definition))
        ->toThrow(InvalidAttributeValueException::class);

    expect(fn () => $type->cast(-5, $definition))
        ->toThrow(InvalidAttributeValueException::class);
});

it('fails loudly when no target entity type is configured', function (): void {
    $type = new EntityRefType();
    $definition = makeEntityRefDefinition([]);

    expect(fn () => $type->cast(1, $definition))
        ->toThrow(InvalidAttributeValueException::class);
});
