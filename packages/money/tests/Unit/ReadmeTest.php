<?php

declare(strict_types=1);

it('provides a README documenting the interface package and pointing to the default driver', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->not->toBeFalsy();
    expect($readme)->toContain('MoneyInterface');
    expect($readme)->toContain('MoneyFactoryInterface');
    expect($readme)->toContain('CurrencyConfigInterface');
    expect($readme)->toContain('MoneyException');
    expect($readme)->toContain('markommerce/money-moneyphp');
    expect($readme)->toContain('multiply');
    expect($readme)->toContain('numeric string');
});
