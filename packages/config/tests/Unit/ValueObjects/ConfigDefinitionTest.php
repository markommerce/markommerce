<?php

declare(strict_types=1);

use Markommerce\Config\ValueObjects\ConfigDefinition;

it(
    'constructs a ConfigDefinition with key, configClass, field, axes, type, defaultValue, and secret',
    function (): void {
        $definition = new ConfigDefinition(
            key: 'markommerce/catalog.grid_page_size',
            configClass: 'App\\Config\\CatalogConfig',
            field: 'gridPageSize',
            axes: ['website', 'store'],
            type: 'int',
            defaultValue: 20,
            secret: false,
        );

        expect($definition)->toBeInstanceOf(ConfigDefinition::class);
    },
);

it(
    'exposes ConfigDefinition properties as public read-only via asymmetric visibility or readonly class',
    function (): void {
        $definition = new ConfigDefinition(
            key: 'markommerce/catalog.grid_page_size',
            configClass: 'App\\Config\\CatalogConfig',
            field: 'gridPageSize',
            axes: ['website', 'store'],
            type: 'int',
            defaultValue: 20,
            secret: false,
        );

        expect($definition->key)->toBe('markommerce/catalog.grid_page_size')
            ->and($definition->configClass)->toBe('App\\Config\\CatalogConfig')
            ->and($definition->field)->toBe('gridPageSize')
            ->and($definition->axes)->toBe(['website', 'store'])
            ->and($definition->type)->toBe('int')
            ->and($definition->defaultValue)->toBe(20)
            ->and($definition->secret)->toBeFalse();
    },
);
