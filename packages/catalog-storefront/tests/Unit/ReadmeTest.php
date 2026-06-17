<?php

declare(strict_types=1);

it(
    'creates packages/catalog-storefront/README.md with title, intro paragraph, Installation, Quick Example, Storefront Route, Layout, and Documentation footer sections',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';

        expect(file_exists($readmePath))->toBeTrue();

        $content = file_get_contents($readmePath);

        expect($content)
            ->toContain('# markommerce/catalog-storefront')
            ->toContain('composer require markommerce/catalog-storefront')
            ->toContain('## Installation')
            ->toContain('## Quick Example')
            ->toContain('## Documentation')
            ->toContain('markommerce.dev/docs/packages/catalog-storefront')
            ->toContain('/catalog/category/{id}');
    },
);

it(
    'it documents the two-column category layout in the catalog-storefront docs',
    function (): void {
        $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/catalog-storefront.md';

        expect(file_exists($docsPath))->toBeTrue();

        $content = (string) file_get_contents($docsPath);

        expect($content)
            // Two-column layout
            ->toContain('TwoColumnsLeftLayout')
            ->toContain('sidebar-left')
            ->toContain('content')
            // Layout definition code block shows actual shipped code
            ->toContain('category_show.php')
            // Cohesive sort/grid/pagination styling
            ->toContain('category.css')
            ->toContain('pagination.css')
            ->toContain('@layer components')
            ->toContain('--mk-')
            // Cross-link to catalog-attribute-storefront
            ->toContain('catalog-attribute-storefront');
    },
);
