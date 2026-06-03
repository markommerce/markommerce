<?php

declare(strict_types=1);

use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Currency\Config\CurrencyConfig;

it('defaults the base currency code to a sensible iso default', function (): void {
    $config = new CurrencyConfig();

    expect($config->base)->toBe('USD');
});

it('registers currency/base key via the Config attribute on base', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CurrencyConfig::class]);

    $definition = $registry->definition(CurrencyConfig::class, 'base');

    expect($definition->key)->toBe('currency/base')
        ->and($definition->defaultValue)->toBe('USD');
});
