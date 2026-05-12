<?php

declare(strict_types=1);

use Markommerce\Catalog\Exception\InvalidProductDataException;

it('constructs InvalidProductDataException variants for empty name empty sku and negative base price', function (): void {
    $emptyName = InvalidProductDataException::emptyName();
    $emptySku = InvalidProductDataException::emptySku();
    $negativePrice = InvalidProductDataException::negativeBasePrice(-500);

    expect($emptyName->getMessage())->not->toBeEmpty();
    expect($emptySku->getMessage())->not->toBeEmpty();
    expect($negativePrice->getMessage())->toContain('-500');
});

it('constructs InvalidProductDataException currencyMismatch carrying both expected and actual currencies in the message', function (): void {
    $exception = InvalidProductDataException::currencyMismatch('USD', 'EUR');

    expect($exception->getMessage())->toContain('USD');
    expect($exception->getMessage())->toContain('EUR');
});
