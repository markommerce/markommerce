<?php

declare(strict_types=1);

use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\ValueObjects\ConfigDefinition;

enum TestColor: string
{
    case Red = 'red';
    case Blue = 'blue';
}

function makeDefinition(string $type): ConfigDefinition
{
    return new ConfigDefinition(
        key: 'test/module.some_key',
        configClass: 'App\\Config\\TestConfig',
        field: 'someKey',
        axes: [],
        type: $type,
        defaultValue: null,
        secret: false,
    );
}

it('returns a string unchanged when target type is string', function (): void {
    $caster = new ValueCaster();
    $result = $caster->cast('hello', makeDefinition('string'));

    expect($result)->toBe('hello');
});

it('returns an int unchanged when target type is int', function (): void {
    $caster = new ValueCaster();
    $result = $caster->cast(42, makeDefinition('int'));

    expect($result)->toBe(42);
});

it('returns a float unchanged when target type is float', function (): void {
    $caster = new ValueCaster();
    $result = $caster->cast(3.14, makeDefinition('float'));

    expect($result)->toBe(3.14);
});

it('returns a bool unchanged when target type is bool', function (): void {
    $caster = new ValueCaster();
    $result = $caster->cast(true, makeDefinition('bool'));

    expect($result)->toBe(true);
});

it('returns an array unchanged when target type is array', function (): void {
    $caster = new ValueCaster();
    $result = $caster->cast(['foo', 'bar'], makeDefinition('array'));

    expect($result)->toBe(['foo', 'bar']);
});

it('converts a backed-enum stored value to the enum instance via tryFrom', function (): void {
    $caster = new ValueCaster();
    $result = $caster->cast('red', makeDefinition(TestColor::class));

    expect($result)->toBe(TestColor::Red);
});

it('throws InvalidConfigValueException when the stored value is a string but target type is int', function (): void {
    $caster = new ValueCaster();

    expect(fn () => $caster->cast('not-a-number', makeDefinition('int')))
        ->toThrow(InvalidConfigValueException::class);
});

it('throws InvalidConfigValueException when a backed enum tryFrom returns null', function (): void {
    $caster = new ValueCaster();

    expect(fn () => $caster->cast('purple', makeDefinition(TestColor::class)))
        ->toThrow(InvalidConfigValueException::class);
});

it(
    'throws InvalidConfigValueException carrying the key, raw value, and declared type in the message',
    function (): void {
        $caster = new ValueCaster();

        try {
            $caster->cast('not-a-number', makeDefinition('int'));
            expect(true)->toBeFalse('Expected exception not thrown');
        } catch (InvalidConfigValueException $e) {
            expect($e->getMessage())
                ->toContain('test/module.some_key')
                ->toContain('not-a-number')
                ->toContain('int');
        }
    },
);
