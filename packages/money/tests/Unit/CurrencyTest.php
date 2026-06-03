<?php

declare(strict_types=1);

use Markommerce\Money\Currency;

it('creates a currency with code scale symbol and name', function (): void {
    $currency = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');

    expect($currency)->toBeInstanceOf(Currency::class);
});

it('exposes code scale symbol and name as readonly properties', function (): void {
    $currency = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');

    expect($currency->code)->toBe('USD')
        ->and($currency->scale)->toBe(2)
        ->and($currency->symbol)->toBe('$')
        ->and($currency->name)->toBe('US Dollar');
});

it('uppercases and accepts a valid three letter iso code', function (): void {
    $currency = new Currency(code: 'usd', scale: 2, symbol: '$', name: 'US Dollar');

    expect($currency->code)->toBe('USD');
});

it('throws InvalidCurrencyException when code is not three letters', function (): void {
    expect(fn () => new Currency(code: 'US', scale: 2, symbol: '$', name: 'US Dollar'))
        ->toThrow(\Markommerce\Money\Exceptions\InvalidCurrencyException::class);

    expect(fn () => new Currency(code: 'USDD', scale: 2, symbol: '$', name: 'US Dollar'))
        ->toThrow(\Markommerce\Money\Exceptions\InvalidCurrencyException::class);

    expect(fn () => new Currency(code: 'US1', scale: 2, symbol: '$', name: 'US Dollar'))
        ->toThrow(\Markommerce\Money\Exceptions\InvalidCurrencyException::class);
});

it('throws InvalidCurrencyException when scale is negative', function (): void {
    expect(fn () => new Currency(code: 'USD', scale: -1, symbol: '$', name: 'US Dollar'))
        ->toThrow(\Markommerce\Money\Exceptions\InvalidCurrencyException::class);
});

it('treats two currencies with the same code as equal via an equals method', function (): void {
    $usd1 = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
    $usd2 = new Currency(code: 'usd', scale: 2, symbol: '$', name: 'United States Dollar');
    $eur = new Currency(code: 'EUR', scale: 2, symbol: '€', name: 'Euro');

    expect($usd1->equals($usd2))->toBeTrue()
        ->and($usd1->equals($eur))->toBeFalse();
});
