<?php

declare(strict_types=1);

use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\Money\RoundingMode;
use Markommerce\Money\Exceptions\CurrencyMismatchException;

function usd(): Currency
{
    return new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
}

function eur(): Currency
{
    return new Currency(code: 'EUR', scale: 2, symbol: '€', name: 'Euro');
}

function jpy(): Currency
{
    return new Currency(code: 'JPY', scale: 0, symbol: '¥', name: 'Japanese Yen');
}

it('creates money from a decimal string and a currency', function (): void {
    $money = Money::of('10.00', usd());

    expect($money)->toBeInstanceOf(Money::class)
        ->and($money->amount())->toBe('10.00')
        ->and($money->currency())->toBeInstanceOf(Currency::class)
        ->and($money->currency()->code)->toBe('USD');
});

it('creates money from minor units using the currency scale', function (): void {
    $money = Money::ofMinor(1000, usd());

    expect($money->amount())->toBe('10.00')
        ->and($money->currency()->code)->toBe('USD');
});

it('adds two money amounts of the same currency', function (): void {
    $a = Money::of('10.00', usd());
    $b = Money::of('5.50', usd());

    $result = $a->add($b);

    expect($result->amount())->toBe('15.50')
        ->and($result->currency()->code)->toBe('USD');
});

it('throws CurrencyMismatchException when adding different currencies', function (): void {
    $usdMoney = Money::of('10.00', usd());
    $eurMoney = Money::of('10.00', eur());

    expect(fn () => $usdMoney->add($eurMoney))
        ->toThrow(CurrencyMismatchException::class);
});

it('multiplies by a factor using an explicit rounding mode', function (): void {
    $money = Money::of('10.00', usd());

    $result = $money->multiply('1.5', RoundingMode::HalfUp);

    expect($result->amount())->toBe('15.00')
        ->and($result->currency()->code)->toBe('USD');
});

it('divides by a divisor using an explicit rounding mode', function (): void {
    $money = Money::of('10.00', usd());

    $result = $money->divide('3', RoundingMode::HalfUp);

    expect($result->amount())->toBe('3.33')
        ->and($result->currency()->code)->toBe('USD');
});

it('throws when dividing by zero', function (): void {
    $money = Money::of('10.00', usd());

    expect(fn () => $money->divide('0', RoundingMode::HalfUp))
        ->toThrow(\Markommerce\Money\Exceptions\DivisionByZeroException::class);
});

it('allocates an amount across ratios without losing minor units', function (): void {
    $money = Money::of('10.00', usd());

    $allocated = $money->allocate([1, 1, 1]);

    expect($allocated)->toHaveCount(3);

    $sum = array_reduce(
        $allocated,
        fn (Money $carry, Money $m) => $carry->add($m),
        Money::of('0', usd()),
    );

    expect($sum->amount())->toBe('10.00');

    $amounts = array_map(fn (Money $m) => $m->amount(), $allocated);
    expect($amounts)->toContain('3.34')
        ->toContain('3.33');
});

it('reports equality only for same currency and amount', function (): void {
    $a = Money::of('10.00', usd());
    $b = Money::of('10.00', usd());
    $c = Money::of('10.00', eur());
    $d = Money::of('9.99', usd());

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and($a->equals($d))->toBeFalse();
});

it('returns a new immutable instance from every arithmetic operation', function (): void {
    $original = Money::of('10.00', usd());

    $added = $original->add(Money::of('5.00', usd()));
    $subtracted = $original->subtract(Money::of('5.00', usd()));
    $multiplied = $original->multiply('2', RoundingMode::HalfUp);
    $divided = $original->divide('2', RoundingMode::HalfUp);

    expect($original->amount())->toBe('10.00')
        ->and($added)->not->toBe($original)
        ->and($subtracted)->not->toBe($original)
        ->and($multiplied)->not->toBe($original)
        ->and($divided)->not->toBe($original);
});
