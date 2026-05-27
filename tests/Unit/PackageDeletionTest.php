<?php

declare(strict_types=1);

it('removes the packages/catalog-market-category-trees directory entirely', function (): void {
    $dir = dirname(__DIR__, 2) . '/packages/catalog-market-category-trees';

    expect(is_dir($dir))->toBeFalse(
        'packages/catalog-market-category-trees/ should have been removed but still exists',
    );
});
