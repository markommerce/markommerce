<?php

declare(strict_types=1);

it('provides a README documenting the moneyphp driver and the rebinding seam', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->not->toBeFalsy();
    expect($readme)->toContain('ext-intl');
    expect($readme)->toContain('CurrencyConfigInterface');
    expect($readme)->toContain('MoneyFactoryInterface');
    expect($readme)->toContain('Preference');
    expect($readme)->toContain('multiply');
});
