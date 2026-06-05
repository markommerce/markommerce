<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Counter\ExactRowCounter;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Boot a minimal container wired from the criteria module.php bindings.
 */
function bootCriteriaContainer(): ContainerInterface
{
    $moduleArray = require dirname(__DIR__, 2) . '/module.php';

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(PreferenceRegistry::class, $preferenceRegistry);

    foreach ($moduleArray['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    return $container;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('binds the pagination strategy interface to the keyset strategy by default', function (): void {
    $moduleArray = require dirname(__DIR__, 2) . '/module.php';

    expect($moduleArray['bindings'])
        ->toHaveKey(PaginationStrategyInterface::class)
        ->and($moduleArray['bindings'][PaginationStrategyInterface::class])
        ->toBe(KeysetPaginationStrategy::class);
});

it('binds the row counter interface to the exact counter by default', function (): void {
    $moduleArray = require dirname(__DIR__, 2) . '/module.php';

    expect($moduleArray['bindings'])
        ->toHaveKey(RowCounterInterface::class)
        ->and($moduleArray['bindings'][RowCounterInterface::class])
        ->toBe(ExactRowCounter::class);
});

it('resolves a working strategy from the container with default bindings', function (): void {
    $container = bootCriteriaContainer();

    $strategy = $container->get(PaginationStrategyInterface::class);

    expect($strategy)->toBeInstanceOf(KeysetPaginationStrategy::class);
});
