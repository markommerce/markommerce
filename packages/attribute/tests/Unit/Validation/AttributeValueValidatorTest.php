<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\Attribute\Type\FacetKind;
use Markommerce\Attribute\Type\SelectType;
use Markommerce\Attribute\Validation\AttributeValueValidator;

function makeValidatorDefinition(
    string $code = 'attr',
    string $type = 'text',
    bool $required = false,
    array $config = [],
): AttributeDefinitionInterface {
    return new class ($code, $type, $required, $config) implements AttributeDefinitionInterface
    {
        public function __construct(
            private readonly string $attrCode,
            private readonly string $attrType,
            private readonly bool $attrRequired,
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
            return $this->attrType;
        }

        public function backing(): AttributeBacking
        {
            return AttributeBacking::Json;
        }

        public function isRequired(): bool
        {
            return $this->attrRequired;
        }

        public function config(): array
        {
            return $this->attrConfig;
        }
    };
}

function makePassthroughType(string $code = 'text'): AttributeTypeInterface
{
    return new class ($code) implements AttributeTypeInterface
    {
        public function __construct(private readonly string $typeCode) {}

        public function code(): string
        {
            return $this->typeCode;
        }

        public function cast(
            mixed $raw,
            AttributeDefinitionInterface $definition,
        ): mixed
        {
            return $raw . '_cast';
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

it('casts a value using the type resolved from the definition', function (): void {
    $registry = new AttributeTypeRegistry();
    $registry->register(makePassthroughType('text'));
    $validator = new AttributeValueValidator($registry);

    $definition = makeValidatorDefinition(code: 'title', type: 'text');

    $result = $validator->validate($definition, 'hello');

    expect($result)->toBe('hello_cast');
});

it('throws when a required attribute receives a null value', function (): void {
    $registry = new AttributeTypeRegistry();
    $registry->register(makePassthroughType('text'));
    $validator = new AttributeValueValidator($registry);

    $definition = makeValidatorDefinition(code: 'title', type: 'text', required: true);

    expect(fn () => $validator->validate($definition, null))
        ->toThrow(InvalidAttributeValueException::class);
});

it('allows null for a non-required attribute', function (): void {
    $registry = new AttributeTypeRegistry();
    $registry->register(makePassthroughType('text'));
    $validator = new AttributeValueValidator($registry);

    $definition = makeValidatorDefinition(code: 'subtitle', type: 'text', required: false);

    $result = $validator->validate($definition, null);

    expect($result)->toBeNull();
});

it('enforces option membership for a select definition', function (): void {
    $registry = new AttributeTypeRegistry();
    $registry->register(new SelectType());
    $validator = new AttributeValueValidator($registry);

    $definition = makeValidatorDefinition(code: 'color', type: 'select');

    $result = $validator->validate($definition, 'red', ['red', 'green', 'blue']);

    expect($result)->toBe('red');
});

it('throws UnknownAttributeTypeException when the definition type is not registered', function (): void {
    $registry = new AttributeTypeRegistry();
    $validator = new AttributeValueValidator($registry);

    $definition = makeValidatorDefinition(code: 'title', type: 'unknown_type');

    expect(fn () => $validator->validate($definition, 'hello'))
        ->toThrow(UnknownAttributeTypeException::class);
});

it('propagates InvalidAttributeValueException from the underlying type', function (): void {
    $throwingType = new class ('text') implements AttributeTypeInterface
    {
        public function __construct(private readonly string $typeCode) {}

        public function code(): string
        {
            return $this->typeCode;
        }

        public function cast(
            mixed $raw,
            AttributeDefinitionInterface $definition,
        ): mixed
        {
            throw InvalidAttributeValueException::forValue($definition->code(), $this->typeCode, (string) $raw);
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

    $registry = new AttributeTypeRegistry();
    $registry->register($throwingType);
    $validator = new AttributeValueValidator($registry);

    $definition = makeValidatorDefinition(code: 'title', type: 'text');

    expect(fn () => $validator->validate($definition, 42))
        ->toThrow(InvalidAttributeValueException::class);
});
