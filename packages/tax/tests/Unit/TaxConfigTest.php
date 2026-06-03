<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Tax\Config\TaxConfig;

it('defaults prices include tax to false', function (): void {
    $config = new TaxConfig();

    expect($config->pricesIncludeTax)->toBeFalse();
});

it('registers tax/prices_include_tax key via the Config attribute on pricesIncludeTax', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([TaxConfig::class]);

    $definition = $registry->definition(TaxConfig::class, 'pricesIncludeTax');

    expect($definition->key)->toBe('tax/prices_include_tax')
        ->and($definition->defaultValue)->toBeFalse();
});
