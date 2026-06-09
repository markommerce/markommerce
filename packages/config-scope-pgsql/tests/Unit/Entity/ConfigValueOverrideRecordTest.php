<?php

declare(strict_types=1);

use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Markommerce\ConfigScope\PgSql\Entity\ConfigValueOverrideRecord;

it('declares the config_value_overrides table via entity metadata', function (): void {
    $reflection = new ReflectionClass(ConfigValueOverrideRecord::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('config_value_overrides');
});

it('declares a unique index on config_key and signature for the overrides table', function (): void {
    $reflection = new ReflectionClass(ConfigValueOverrideRecord::class);
    $attributes = $reflection->getAttributes(Index::class);

    expect($attributes)->toHaveCount(1);

    $index = $attributes[0]->newInstance();

    expect($index->columns)->toBe(['config_key', 'signature']);
    expect($index->unique)->toBeTrue();
});
