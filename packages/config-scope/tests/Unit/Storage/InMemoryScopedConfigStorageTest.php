<?php

declare(strict_types=1);

use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;

it(
    'returns an empty array from InMemoryScopedConfigStorage loadOverrides for an unknown config key',
    function (): void {
        $storage = new InMemoryScopedConfigStorage();

        expect($storage->loadOverrides('some/unknown.key'))->toBe([]);
    },
);

it(
    'stores and retrieves an override via InMemoryScopedConfigStorage saveOverride + loadOverrides round-trip',
    function (): void {
        $storage = new InMemoryScopedConfigStorage();
        $storage->saveOverride('app/design.theme', 'locale:en', 'modern');

        expect($storage->loadOverrides('app/design.theme'))->toBe(['locale:en' => 'modern']);
    },
);

it(
    'replaces an existing override under the same (key, signature) pair when saveOverride is called twice',
    function (): void {
        $storage = new InMemoryScopedConfigStorage();
        $storage->saveOverride('app/design.theme', 'locale:en', 'modern');
        $storage->saveOverride('app/design.theme', 'locale:en', 'classic');

        expect($storage->loadOverrides('app/design.theme'))->toBe(['locale:en' => 'classic']);
    },
);

it(
    'removes a single override via InMemoryScopedConfigStorage deleteOverride leaving other overrides intact',
    function (): void {
        $storage = new InMemoryScopedConfigStorage();
        $storage->saveOverride('app/design.theme', 'locale:en', 'modern');
        $storage->saveOverride('app/design.theme', 'locale:fr', 'classic');
        $storage->deleteOverride('app/design.theme', 'locale:en');

        expect($storage->loadOverrides('app/design.theme'))->toBe(['locale:fr' => 'classic']);
    },
);

it('returns multiple keys as a nested map from InMemoryScopedConfigStorage loadManyOverrides', function (): void {
    $storage = new InMemoryScopedConfigStorage();
    $storage->saveOverride('app/design.theme', 'locale:en', 'modern');
    $storage->saveOverride('app/design.font', 'locale:fr', 'serif');

    $result = $storage->loadManyOverrides(['app/design.theme', 'app/design.font', 'unknown/key']);

    expect($result)->toBe([
        'app/design.theme' => ['locale:en' => 'modern'],
        'app/design.font' => ['locale:fr' => 'serif'],
        'unknown/key' => [],
    ]);
});
