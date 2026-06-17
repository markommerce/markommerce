<?php

declare(strict_types=1);

it(
    'it documents the catalog-attribute-storefront layered navigation and query-param convention in its README',
    function (): void {
        $readmePath = dirname(__DIR__) . '/README.md';

        expect(file_exists($readmePath))->toBeTrue();

        $content = (string) file_get_contents($readmePath);

        expect($content)
            ->toContain('# markommerce/catalog-attribute-storefront')
            ->toContain('composer require markommerce/catalog-attribute-storefront')
            ->toContain('## Installation')
            ->toContain('## Quick Example')
            ->toContain('## Documentation')
            ->toContain('markommerce.dev/docs/packages/catalog-attribute-storefront')
            // Query-param convention
            ->toContain('filter[color][]=red')
            ->toContain('filter[size][]=L')
            // Key classes
            ->toContain('AttributeProductListFilter')
            ->toContain('LayeredNavigationAssembler')
            ->toContain('FilterParamParser')
            // Key concepts
            ->toContain('disjunctive')
            ->toContain('catalog-attribute-index')
            ->toContain('ProductListFilterInterface');
    },
);

it(
    'it documents the catalog product-list filter registry extension point',
    function (): void {
        $catalogReadmePath = dirname(__DIR__, 2) . '/catalog/README.md';

        expect(file_exists($catalogReadmePath))->toBeTrue();

        $content = (string) file_get_contents($catalogReadmePath);

        expect($content)
            ->toContain('ProductListFilterInterface')
            ->toContain('ProductListFilterRegistry')
            ->toContain('FilterSelection');
    },
);
