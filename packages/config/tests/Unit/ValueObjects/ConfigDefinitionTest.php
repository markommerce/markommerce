<?php

declare(strict_types=1);

use Markommerce\Config\ValueObjects\ConfigDefinition;

it(
    'rejects a constructor call passing an axes named argument to ConfigDefinition',
    function (): void {
        expect(fn () => new ConfigDefinition(
            key: 'markommerce/catalog.grid_page_size',
            configClass: 'App\\Config\\CatalogConfig',
            field: 'gridPageSize',
            axes: ['website', 'store'],
            type: 'int',
            defaultValue: 20,
            secret: false,
        ))->toThrow(Error::class);
    },
);

it(
    'constructs ConfigDefinition with key, configClass, field, type, defaultValue, and secret but no axes property',
    function (): void {
        $definition = new ConfigDefinition(
            key: 'markommerce/catalog.grid_page_size',
            configClass: 'App\\Config\\CatalogConfig',
            field: 'gridPageSize',
            type: 'int',
            defaultValue: 20,
            secret: false,
        );

        expect($definition)->toBeInstanceOf(ConfigDefinition::class)
            ->and($definition->key)->toBe('markommerce/catalog.grid_page_size')
            ->and($definition->configClass)->toBe('App\\Config\\CatalogConfig')
            ->and($definition->field)->toBe('gridPageSize')
            ->and($definition->type)->toBe('int')
            ->and($definition->defaultValue)->toBe(20)
            ->and($definition->secret)->toBeFalse();
    },
);
