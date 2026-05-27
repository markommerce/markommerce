<?php

declare(strict_types=1);

it('FEATURES.md Tier 3 package count reflects 17 packages', function (): void {
    $file = dirname(__DIR__, 2) . '/FEATURES.md';

    expect(file_exists($file))->toBeTrue();

    $content = (string) file_get_contents($file);

    // 18 packages was the old count including catalog-market-category-trees separately
    // 17 packages is the new count after merging catalog-market-category-trees into catalog-market
    expect($content)->toContain('17 packages')
        ->and($content)->not->toContain('18 packages');
});
