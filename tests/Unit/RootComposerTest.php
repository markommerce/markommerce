<?php

declare(strict_types=1);

it('removes markommerce/catalog-market-category-trees from the root composer.json require block', function (): void {
    $rootComposerPath = dirname(__DIR__, 2) . '/composer.json';
    $rootComposer = json_decode((string) file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->not->toHaveKey('markommerce/catalog-market-category-trees');
});
