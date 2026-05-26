<?php

declare(strict_types=1);

use Markommerce\Config\Cache\RequestConfigCache;

it(
    'caches the resolved value on first call and returns the cached value on second call for the same config-key and scope projection',
    function (): void {
        $cache = new RequestConfigCache();
        $callCount = 0;

        $loader = function () use (&$callCount): string {
            $callCount++;

            return 'resolved-value';
        };

        $first = $cache->get('my-key|store:eu', $loader);
        $second = $cache->get('my-key|store:eu', $loader);

        expect($first)->toBe('resolved-value')
            ->and($second)->toBe('resolved-value')
            ->and($callCount)->toBe(1);
    },
);

it(
    'caches resolved null values and does not re-invoke the loader on subsequent reads of the same key',
    function (): void {
        $cache = new RequestConfigCache();
        $callCount = 0;

        $loader = function () use (&$callCount): mixed {
            $callCount++;

            return null;
        };

        $first = $cache->get('null-key', $loader);
        $second = $cache->get('null-key', $loader);

        expect($first)->toBeNull()
            ->and($second)->toBeNull()
            ->and($callCount)->toBe(1);
    },
);

it('caches independently for the same config under different ScopeContext projections', function (): void {
    $cache = new RequestConfigCache();
    $callCount = 0;

    $loaderEu = function () use (&$callCount): string {
        $callCount++;

        return 'value-for-eu';
    };

    $loaderDe = function () use (&$callCount): string {
        $callCount++;

        return 'value-for-de';
    };

    $euKey = 'my-config|store:eu';
    $deKey = 'my-config|store:de';

    $euResult = $cache->get($euKey, $loaderEu);
    $deResult = $cache->get($deKey, $loaderDe);

    // Second reads should hit cache
    $euResult2 = $cache->get($euKey, $loaderEu);
    $deResult2 = $cache->get($deKey, $loaderDe);

    expect($euResult)->toBe('value-for-eu')
        ->and($deResult)->toBe('value-for-de')
        ->and($euResult2)->toBe('value-for-eu')
        ->and($deResult2)->toBe('value-for-de')
        ->and($callCount)->toBe(2); // each key loaded exactly once
});

it('it invalidates all cache entries for a given config key when invalidatePrefix is called', function (): void {
    $cache = new RequestConfigCache();

    // Populate entries for 'my-config' key
    $cache->get('my-config', fn () => 'unscoped-value');
    $cache->get('my-config|store:eu', fn () => 'eu-value');
    $cache->get('my-config|store:de', fn () => 'de-value');

    $cache->invalidatePrefix('my-config');

    // After invalidation, loader should be called again
    $callCount = 0;
    $cache->get('my-config', function () use (&$callCount): string {
        $callCount++;

        return 'new-value';
    });
    $cache->get('my-config|store:eu', function () use (&$callCount): string {
        $callCount++;

        return 'new-eu-value';
    });

    expect($callCount)->toBe(2);
});

it('it does not invalidate entries for unrelated config keys when invalidatePrefix is called', function (): void {
    $cache = new RequestConfigCache();

    // Populate entries for two different config keys
    $cache->get('my-config|store:eu', fn () => 'my-config-eu');
    $cache->get('other-config|store:eu', fn () => 'other-config-eu');
    $cache->get('my-config-extra|store:eu', fn () => 'my-config-extra-eu');

    // Invalidate only 'my-config'
    $cache->invalidatePrefix('my-config');

    $otherCallCount = 0;
    $cache->get('other-config|store:eu', function () use (&$otherCallCount): string {
        $otherCallCount++;

        return 'new-other';
    });

    // 'my-config-extra' should NOT be invalidated (it starts with 'my-config' but isn't 'my-config|...')
    $extraCallCount = 0;
    $cache->get('my-config-extra|store:eu', function () use (&$extraCallCount): string {
        $extraCallCount++;

        return 'new-extra';
    });

    expect($otherCallCount)->toBe(0) // other-config still cached
        ->and($extraCallCount)->toBe(0); // my-config-extra still cached
});

it('it clears every entry when clear() is called', function (): void {
    $cache = new RequestConfigCache();

    $cache->get('config-a', fn () => 'value-a');
    $cache->get('config-b|store:eu', fn () => 'value-b-eu');
    $cache->get('config-c|locale:fr', fn () => 'value-c-fr');

    $cache->clear();

    $callCount = 0;
    $loader = function () use (&$callCount): string {
        $callCount++;

        return 'fresh-value';
    };

    $cache->get('config-a', $loader);
    $cache->get('config-b|store:eu', $loader);
    $cache->get('config-c|locale:fr', $loader);

    expect($callCount)->toBe(3);
});
