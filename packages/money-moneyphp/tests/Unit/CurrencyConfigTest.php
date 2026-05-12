<?php

declare(strict_types=1);

use Markommerce\Money\CurrencyConfigInterface;
use Markommerce\Money\Moneyphp\CurrencyConfig;

it('implements CurrencyConfigInterface in CurrencyConfig', function (): void {
    expect(class_exists(CurrencyConfig::class))->toBeTrue();

    $reflection = new ReflectionClass(CurrencyConfig::class);
    expect($reflection->implementsInterface(CurrencyConfigInterface::class))->toBeTrue();
    expect($reflection->isFinal())->toBeFalse();
});

it('returns USD from CurrencyConfig getDefault', function (): void {
    $config = new CurrencyConfig();
    expect($config->getDefault())->toBe('USD');
});

it('carries a multi-store refactor docblock on CurrencyConfig', function (): void {
    $reflection = new ReflectionClass(CurrencyConfig::class);
    $docComment = $reflection->getDocComment();

    expect($docComment)->toBeString();
    expect($docComment)->toContain('@todo multi-store');
});
