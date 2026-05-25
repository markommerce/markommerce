<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;

it('constructs with a required key string', function (): void {
    $config = new Config(key: 'catalog/general/welcome_message');

    expect($config->key)->toBe('catalog/general/welcome_message');
});

it('defaults the secret flag to false when omitted', function (): void {
    $config = new Config(key: 'catalog/general/welcome_message');

    expect($config->secret)->toBeFalse();
});

it('accepts secret: true for properties needing encryption-at-rest', function (): void {
    $config = new Config(key: 'catalog/general/api_key', secret: true);

    expect($config->secret)->toBeTrue();
});

it('rejects an empty key string with a clear exception at construction time', function (): void {
    expect(fn () => new Config(key: ''))->toThrow(InvalidArgumentException::class);
});

it('is targetable to properties only (TARGET_PROPERTY)', function (): void {
    $reflection = new ReflectionClass(Config::class);
    $attributes = $reflection->getAttributes(Attribute::class);
    $attribute = $attributes[0]->newInstance();

    expect($attributes)->toHaveCount(1)
        ->and($attribute->flags)->toBe(Attribute::TARGET_PROPERTY);
});

it('is a readonly class so the metadata cannot mutate after construction', function (): void {
    $reflection = new ReflectionClass(Config::class);

    expect($reflection->isReadOnly())->toBeTrue();
});
