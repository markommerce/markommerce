<?php

declare(strict_types=1);

use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Counter\ExactRowCounter;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

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
