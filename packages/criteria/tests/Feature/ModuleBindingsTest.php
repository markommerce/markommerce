<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
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

it('resolves a working strategy from the container with default bindings', function (): void {
    $container = bootCriteriaContainer();

    $strategy = $container->get(PaginationStrategyInterface::class);

    expect($strategy)->toBeInstanceOf(KeysetPaginationStrategy::class);
});
