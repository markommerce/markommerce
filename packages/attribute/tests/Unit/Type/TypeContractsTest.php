<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\Attribute\Type\FacetKind;

it('declares code cast serialize deserialize and facetKind on AttributeTypeInterface', function (): void {
    $reflection = new ReflectionClass(AttributeTypeInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('code'))->toBeTrue()
        ->and($reflection->hasMethod('cast'))->toBeTrue()
        ->and($reflection->hasMethod('serialize'))->toBeTrue()
        ->and($reflection->hasMethod('deserialize'))->toBeTrue()
        ->and($reflection->hasMethod('facetKind'))->toBeTrue();

    $codeReturn = $reflection->getMethod('code')->getReturnType();
    assert($codeReturn instanceof ReflectionNamedType);
    expect($codeReturn->getName())->toBe('string');

    $castReturn = $reflection->getMethod('cast')->getReturnType();
    assert($castReturn instanceof ReflectionNamedType);
    expect($castReturn->getName())->toBe('mixed');

    $serializeReturn = $reflection->getMethod('serialize')->getReturnType();
    assert($serializeReturn instanceof ReflectionNamedType);
    expect($serializeReturn->getName())->toBe('mixed');

    $deserializeReturn = $reflection->getMethod('deserialize')->getReturnType();
    assert($deserializeReturn instanceof ReflectionNamedType);
    expect($deserializeReturn->getName())->toBe('mixed');

    $facetKindReturn = $reflection->getMethod('facetKind')->getReturnType();
    assert($facetKindReturn instanceof ReflectionNamedType);
    expect($facetKindReturn->getName())->toBe(FacetKind::class);
});

it('declares the definition getters on AttributeDefinitionInterface', function (): void {
    $reflection = new ReflectionClass(AttributeDefinitionInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('code'))->toBeTrue()
        ->and($reflection->hasMethod('entityType'))->toBeTrue()
        ->and($reflection->hasMethod('type'))->toBeTrue()
        ->and($reflection->hasMethod('backing'))->toBeTrue()
        ->and($reflection->hasMethod('isRequired'))->toBeTrue()
        ->and($reflection->hasMethod('config'))->toBeTrue();

    $codeReturn = $reflection->getMethod('code')->getReturnType();
    assert($codeReturn instanceof ReflectionNamedType);
    expect($codeReturn->getName())->toBe('string');

    $entityTypeReturn = $reflection->getMethod('entityType')->getReturnType();
    assert($entityTypeReturn instanceof ReflectionNamedType);
    expect($entityTypeReturn->getName())->toBe('string');

    $typeReturn = $reflection->getMethod('type')->getReturnType();
    assert($typeReturn instanceof ReflectionNamedType);
    expect($typeReturn->getName())->toBe('string');

    $backingReturn = $reflection->getMethod('backing')->getReturnType();
    assert($backingReturn instanceof ReflectionNamedType);
    expect($backingReturn->getName())->toBe(AttributeBacking::class);

    $isRequiredReturn = $reflection->getMethod('isRequired')->getReturnType();
    assert($isRequiredReturn instanceof ReflectionNamedType);
    expect($isRequiredReturn->getName())->toBe('bool');

    $configReturn = $reflection->getMethod('config')->getReturnType();
    assert($configReturn instanceof ReflectionNamedType);
    expect($configReturn->getName())->toBe('array');
});

it('types cast against AttributeDefinitionInterface not the entity', function (): void {
    $castMethod = new ReflectionMethod(AttributeTypeInterface::class, 'cast');
    $params = $castMethod->getParameters();

    expect($params)->toHaveCount(2);

    $rawParam = $params[0];
    expect($rawParam->getName())->toBe('raw');

    $definitionParam = $params[1];
    expect($definitionParam->getName())->toBe('definition');

    $definitionType = $definitionParam->getType();
    assert($definitionType instanceof ReflectionNamedType);
    expect($definitionType->getName())->toBe(AttributeDefinitionInterface::class);
});

it('exposes Column and Json cases on the AttributeBacking enum', function (): void {
    expect(enum_exists(AttributeBacking::class))->toBeTrue();

    $cases = AttributeBacking::cases();
    $caseNames = array_map(fn ($case) => $case->name, $cases);

    expect($caseNames)->toContain('Column')
        ->and($caseNames)->toContain('Json')
        ->and($cases)->toHaveCount(2);
});

it('exposes Term Range and None cases on the FacetKind enum', function (): void {
    expect(enum_exists(FacetKind::class))->toBeTrue();

    $cases = FacetKind::cases();
    $caseNames = array_map(fn ($case) => $case->name, $cases);

    expect($caseNames)->toContain('Term')
        ->and($caseNames)->toContain('Range')
        ->and($caseNames)->toContain('None')
        ->and($cases)->toHaveCount(3);
});

it('allows a test double implementing AttributeTypeInterface to be instantiated', function (): void {
    $double = new class () implements AttributeTypeInterface
    {
        public function code(): string
        {
            return 'test';
        }

        /**
         * @throws InvalidAttributeValueException
         */
        public function cast(
            mixed $raw,
            AttributeDefinitionInterface $definition,
        ): mixed {
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

    expect($double)->toBeInstanceOf(AttributeTypeInterface::class)
        ->and($double->code())->toBe('test')
        ->and($double->facetKind())->toBe(FacetKind::None);
});
