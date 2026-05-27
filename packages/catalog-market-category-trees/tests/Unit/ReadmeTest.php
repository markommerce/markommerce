<?php

declare(strict_types=1);

it('markommerce/catalog-market-category-trees README documents installation, the resolver, the assignment service, and the deleteTree plugin guard', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('## Installation')
        ->toContain('composer require markommerce/catalog-market-category-trees')
        ->toContain('markommerce/catalog')
        ->toContain('markommerce/market')
        ->toContain('CategoryTreeMarketResolver')
        ->toContain('resolveTreeForMarket')
        ->toContain('CategoryTreeMarketAssignmentService')
        ->toContain('assignTreeToMarket')
        ->toContain('CategoryTreeServiceDeletePlugin')
        ->toContain('TreeHasMarketAssignmentsException');
});

it('each new package ships a ReadmeTest asserting the README contains its required sections', function (): void {
    $packagesRoot = dirname(__DIR__, 4);

    expect(file_exists($packagesRoot . '/packages/market/tests/ReadmeTest.php'))->toBeTrue();
    expect(file_exists($packagesRoot . '/packages/catalog-market/tests/ReadmeTest.php'))->toBeTrue();
    expect(file_exists($packagesRoot . '/packages/catalog-market-category-trees/tests/Unit/ReadmeTest.php'))->toBeTrue();
});
