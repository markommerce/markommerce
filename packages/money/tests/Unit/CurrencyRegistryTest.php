<?php

declare(strict_types=1);

use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\Currency;
use Markommerce\Money\DefaultCurrencyRegistry;
use Markommerce\Money\Exceptions\UnknownCurrencyException;

it('returns a currency value object for a known iso code', function (): void {
    $registry = new DefaultCurrencyRegistry();

    $currency = $registry->get('USD');

    expect($currency)->toBeInstanceOf(Currency::class)
        ->and($currency->code)->toBe('USD');
});

it('reports whether a currency code is known', function (): void {
    $registry = new DefaultCurrencyRegistry();

    expect($registry->has('USD'))->toBeTrue()
        ->and($registry->has('XYZ'))->toBeFalse();
});

it('throws UnknownCurrencyException for an unknown code', function (): void {
    $registry = new DefaultCurrencyRegistry();

    expect(fn () => $registry->get('XYZ'))
        ->toThrow(UnknownCurrencyException::class);
});

it('seeds JPY with a scale of zero', function (): void {
    $registry = new DefaultCurrencyRegistry();

    $jpy = $registry->get('JPY');

    expect($jpy->scale)->toBe(0);
});

it('returns all seeded currencies keyed by code', function (): void {
    $registry = new DefaultCurrencyRegistry();

    $all = $registry->all();

    expect($all)->toBeArray()
        ->and(array_keys($all))->toContain('USD', 'EUR', 'GBP', 'JPY', 'PLN', 'CHF');

    foreach ($all as $code => $currency) {
        expect($currency)->toBeInstanceOf(Currency::class)
            ->and($currency->code)->toBe($code);
    }
});

it('binds the default registry to the interface in module.php', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(CurrencyRegistryInterface::class)
        ->and($module['bindings'][CurrencyRegistryInterface::class])->toBe(DefaultCurrencyRegistry::class);
});
