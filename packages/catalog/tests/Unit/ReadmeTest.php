<?php

declare(strict_types=1);

it(
    'the catalog README documents the package name and a one-line summary',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);

        expect($content)->toContain('# markommerce/catalog');
    },
);

it('the catalog README includes an installation section', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Installation')
        ->and($content)->toContain('composer require markommerce/catalog');
});

it('the catalog README documents the Product and Category entities', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('Product')
        ->and($content)->toContain('Category')
        ->and($content)->toContain('sku')
        ->and($content)->toContain('locale');
});

it('the catalog README cross-links to markommerce/catalog-storefront for the storefront route', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('catalog-storefront');
});

it('the catalog README documents the catalog seeder', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('catalog')
        ->and($content)->toContain('seeder')
        ->and($content)->not->toContain('setOverride');
});

it(
    'updates packages/catalog/README.md to note that scope is no longer required and link to catalog-scope',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);
    
        expect($content)
            ->toContain('catalog-scope')
            ->toContain('optional');
    }
);

it(
    'it trims packages/catalog/README.md to remove the Storefront Route section, the layout snippet, and the ProductGridComponent overview, replacing them with a cross-link to catalog-storefront',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);
    
        expect($content)->toContain('catalog-storefront');
    }
);

it(
    'markommerce/catalog README cross-links to markommerce/catalog-market-category-trees in a Market-aware multi-tree section',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);
    
        expect($content)->toContain('catalog-market-category-trees');
    }
);

it(
    'the updated catalog ReadmeTest asserts the new cross-link to markommerce/catalog-market-category-trees',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);
    
        expect($content)->toContain('catalog-market-category-trees');
    }
);
