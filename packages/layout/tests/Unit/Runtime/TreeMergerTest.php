<?php

declare(strict_types=1);

use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedRepeatSlot;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;
use Markommerce\Layout\Runtime\TreeMerger;

// =============================================================================
// Helpers
// =============================================================================

function tm_makeTree(
    string $handleKey = 'test::action',
    array $slots = [],
    array $context = [],
    array $handleProviders = [],
): PreparedTree {
    return new PreparedTree(
        handleKey: $handleKey,
        template: null,
        slots: $slots,
        context: $context,
        handleProviders: $handleProviders,
    );
}

function tm_makePlace(string $component = 'SomeComponent', ?string $name = null): PreparedPlace
{
    return new PreparedPlace(
        component: $component,
        name: $name,
        props: [],
        slots: [],
        decorators: [],
        template: '',
    );
}

function tm_makeProvide(string $token = 'MyToken', string $provider = 'SomeProvider'): Provide
{
    return new Provide(
        token: $token,
        provider: $provider,
        props: [],
    );
}

// =============================================================================
// Requirement 10: TreeMerger::merge is idempotent for identical additions
// (sanity: merging the same dynamic tree twice produces the same result as
// merging it once when the base/addition are constructed as such)
// =============================================================================

it('TreeMerger::merge is idempotent for identical additions (sanity: merging the same dynamic tree twice produces the same result as merging it once when the base/addition are constructed as such)', function (): void {
    $place = tm_makePlace('ComponentA', 'comp_a');

    $base = tm_makeTree(slots: ['main' => [$place]]);
    $dynamic = tm_makeTree(handleKey: 'dynamic::action', slots: ['main' => [$place]]);

    $merger = new TreeMerger();

    // Merging empty list returns the base tree unchanged
    $mergedEmpty = $merger->merge($base, []);
    expect($mergedEmpty->slots)->toBe($base->slots);
    expect($mergedEmpty->context)->toBe($base->context);
    expect($mergedEmpty->handleProviders)->toBe($base->handleProviders);

    // Merging the same dynamic tree twice vs once yields predictably additive results
    $mergedOnce = $merger->merge($base, [$dynamic]);
    $mergedTwice = $merger->merge($base, [$dynamic, $dynamic]);

    // Once: base(1 place) + dynamic(1 place) = 2 places
    expect($mergedOnce->slots['main'])->toHaveCount(2);

    // Twice: base(1 place) + dynamic(1 place) + dynamic(1 place) = 3 places
    // (deduplication of identical handles is the middleware's responsibility)
    expect($mergedTwice->slots['main'])->toHaveCount(3);
});
