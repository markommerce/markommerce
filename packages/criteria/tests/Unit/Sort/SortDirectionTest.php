<?php

declare(strict_types=1);

use Markommerce\Criteria\Sort\SortDirection;

it('exposes ascending and descending sort directions', function (): void {
    expect(SortDirection::Ascending->value)->toBe('ASC')
        ->and(SortDirection::Descending->value)->toBe('DESC');
});
