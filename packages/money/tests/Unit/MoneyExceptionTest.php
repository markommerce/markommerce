<?php

declare(strict_types=1);

use Markommerce\Money\MoneyException;

it('constructs a currency mismatch exception carrying expected currency actual currency and the operation name in the message', function (): void {
    $exception = MoneyException::currencyMismatch('USD', 'EUR', 'add');

    expect($exception->getMessage())
        ->toContain('USD')
        ->toContain('EUR')
        ->toContain('add');
});

it('constructs an invalid allocation ratios exception carrying the failure reason in the message', function (): void {
    $exception = MoneyException::invalidAllocationRatios('ratios array must not be empty');

    expect($exception->getMessage())
        ->toContain('ratios array must not be empty');
});

it('constructs an invalid multiply factor exception carrying both the factor and the reason', function (): void {
    $exception = MoneyException::invalidMultiplyFactor('abc', 'not a valid numeric string');

    expect($exception->getMessage())
        ->toContain('abc')
        ->toContain('not a valid numeric string');
});

it('provides a non-empty context and suggestion on every factory method', function (): void {
    $exceptions = [
        MoneyException::currencyMismatch('USD', 'EUR', 'add'),
        MoneyException::invalidAllocationRatios('ratios array must not be empty'),
        MoneyException::invalidMultiplyFactor('abc', 'not a valid numeric string'),
    ];

    foreach ($exceptions as $exception) {
        expect($exception->getContext())->not->toBeEmpty();
        expect($exception->getSuggestion())->not->toBeEmpty();
    }
});

it('extends MarkoException so handlers catching MarkoException also catch MoneyException', function (): void {
    $exception = MoneyException::currencyMismatch('USD', 'EUR', 'add');

    expect($exception)->toBeInstanceOf(\Marko\Core\Exceptions\MarkoException::class);
});
