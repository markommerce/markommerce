<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Attribute\Exceptions\AttributeDefinitionNotFoundException;
use Markommerce\Attribute\Exceptions\DuplicateAttributeCodeException;
use Markommerce\Attribute\Exceptions\InvalidAttributeOptionException;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Exceptions\ReservedAttributeCodeException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;

it('builds InvalidAttributeValueException with message context and suggestion', function (): void {
    $exception = InvalidAttributeValueException::forValue(
        code: 'color',
        type: 'select',
        raw: 'not-a-color',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->not->toBeEmpty()
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('builds UnknownAttributeTypeException naming the unknown type code', function (): void {
    $exception = UnknownAttributeTypeException::forType('fancy_type');

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('fancy_type')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('builds DuplicateAttributeCodeException naming the entity type and code', function (): void {
    $exception = DuplicateAttributeCodeException::forCode(
        entityType: 'product',
        code: 'color',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('product')
        ->and($exception->getMessage())->toContain('color')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('builds ReservedAttributeCodeException naming the entity type and code', function (): void {
    $exception = ReservedAttributeCodeException::forCode(
        entityType: 'product',
        code: 'id',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('product')
        ->and($exception->getMessage())->toContain('id')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('builds AttributeDefinitionNotFoundException naming the entity type and code', function (): void {
    $exception = AttributeDefinitionNotFoundException::forCode(
        entityType: 'product',
        code: 'weight',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('product')
        ->and($exception->getMessage())->toContain('weight')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('builds InvalidAttributeOptionException naming the attribute code and option value', function (): void {
    $exception = InvalidAttributeOptionException::forValue(
        code: 'color',
        optionValue: 'ultraviolet',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('color')
        ->and($exception->getMessage())->toContain('ultraviolet')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
