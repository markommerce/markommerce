<?php

declare(strict_types=1);

use Markommerce\Money\CurrencyConfigInterface;
use Markommerce\Money\MoneyFactoryInterface;
use Markommerce\Money\Moneyphp\CurrencyConfig;
use Markommerce\Money\Moneyphp\MoneyFactory;

it('returns an array with a bindings key from module.php', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    expect(file_exists($modulePath))->toBeTrue('module.php should exist');

    $module = require $modulePath;

    expect($module)->toBeArray();
    expect($module)->toHaveKey('bindings');
});

it('binds CurrencyConfigInterface to CurrencyConfig in module.php', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(CurrencyConfigInterface::class);
    expect($bindings[CurrencyConfigInterface::class])->toBe(CurrencyConfig::class);

    expect(interface_exists(CurrencyConfigInterface::class))->toBeTrue();
    expect(class_exists(CurrencyConfig::class))->toBeTrue();

    $reflection = new ReflectionClass(CurrencyConfig::class);
    expect($reflection->implementsInterface(CurrencyConfigInterface::class))->toBeTrue();
});

it('binds MoneyFactoryInterface to MoneyFactory in module.php', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $bindings = $module['bindings'];

    expect($bindings)->toHaveKey(MoneyFactoryInterface::class);
    expect($bindings[MoneyFactoryInterface::class])->toBe(MoneyFactory::class);

    expect(interface_exists(MoneyFactoryInterface::class))->toBeTrue();
    expect(class_exists(MoneyFactory::class))->toBeTrue();

    $reflection = new ReflectionClass(MoneyFactory::class);
    expect($reflection->implementsInterface(MoneyFactoryInterface::class))->toBeTrue();
});
