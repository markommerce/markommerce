<?php

declare(strict_types=1);

it('docs/src/content/docs/packages/catalog-market-category-trees.md no longer exists', function (): void {
    $file = dirname(__DIR__, 2) . '/docs/src/content/docs/packages/catalog-market-category-trees.md';

    expect(file_exists($file))->toBeFalse(
        'docs/src/content/docs/packages/catalog-market-category-trees.md should have been deleted but still exists',
    );
});
