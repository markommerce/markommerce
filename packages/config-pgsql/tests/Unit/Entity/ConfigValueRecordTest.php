<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Markommerce\Config\PgSql\Entity\ConfigValueRecord;

it('declares the config_values table via entity metadata', function (): void {
    $reflection = new ReflectionClass(ConfigValueRecord::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('config_values');
});

it('declares config_values columns config_key value version and updated_at', function (): void {
    $reflection = new ReflectionClass(ConfigValueRecord::class);

    expect($reflection->hasProperty('configKey'))->toBeTrue();
    expect($reflection->hasProperty('value'))->toBeTrue();
    expect($reflection->hasProperty('version'))->toBeTrue();
    expect($reflection->hasProperty('updatedAt'))->toBeTrue();

    $configKeyAttrs = $reflection->getProperty('configKey')->getAttributes(Column::class);
    expect($configKeyAttrs)->toHaveCount(1);
    $configKeyCol = $configKeyAttrs[0]->newInstance();
    expect($configKeyCol->length)->toBe(255);

    $valueAttrs = $reflection->getProperty('value')->getAttributes(Column::class);
    expect($valueAttrs)->toHaveCount(1);
    $valueCol = $valueAttrs[0]->newInstance();
    expect($valueCol->type)->toBe('jsonb');
    expect($valueCol->nullable)->toBeTrue();

    $versionAttrs = $reflection->getProperty('version')->getAttributes(Column::class);
    expect($versionAttrs)->toHaveCount(1);
    $versionCol = $versionAttrs[0]->newInstance();
    expect($versionCol->type)->toBe('integer');
    expect($versionCol->nullable)->toBeFalse();

    $updatedAtAttrs = $reflection->getProperty('updatedAt')->getAttributes(Column::class);
    expect($updatedAtAttrs)->toHaveCount(1);
    $updatedAtCol = $updatedAtAttrs[0]->newInstance();
    expect($updatedAtCol->name)->toBe('updated_at');
    expect($updatedAtCol->type)->toBe('timestamptz');
    expect($updatedAtCol->nullable)->toBeFalse();
});

it('marks config_key as the primary key on config_values', function (): void {
    $reflection = new ReflectionClass(ConfigValueRecord::class);
    $property = $reflection->getProperty('configKey');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->primaryKey)->toBeTrue();
    expect($column->name)->toBe('config_key');
});

it('documents the intentionally omitted GIN index in a code comment', function (): void {
    $reflection = new ReflectionClass(ConfigValueRecord::class);
    $file = $reflection->getFileName();
    $contents = file_get_contents($file);

    expect($contents)->toContain('GIN');
    expect($contents)->toContain('intentionally omitted');
});
