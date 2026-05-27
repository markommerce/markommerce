<?php

declare(strict_types=1);

it('ships a docs page at docs/src/content/docs/packages/market.md with axis declaration content', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/market.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/market');
    expect($content)->toContain('description:');

    // No ## Overview heading (DOCS-STANDARDS forbids it)
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/market');
    expect($content)->toContain('## Related Packages');

    // Axis declaration content
    expect($content)->toContain('market');
    expect($content)->toContain('config/scope.php');

    // Cross-link to catalog-market-category-trees
    expect($content)->toContain('markommerce/catalog-market-category-trees');
});

it('ships a docs page at docs/src/content/docs/packages/catalog-market.md that documents placeholder status', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-market.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/catalog-market');
    expect($content)->toContain('description:');

    // No ## Overview heading
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/catalog-market');
    expect($content)->toContain('## Related Packages');

    // Placeholder status documented
    expect($content)->toContain('placeholder');

    // Cross-links
    expect($content)->toContain('markommerce/catalog-scope');
    expect($content)->toContain('markommerce/market');
});

it('ships a docs page at docs/src/content/docs/packages/catalog-market-category-trees.md that documents the resolver, assignment service, and delete plugin', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-market-category-trees.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/catalog-market-category-trees');
    expect($content)->toContain('description:');

    // No ## Overview heading
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/catalog-market-category-trees');
    expect($content)->toContain('## Usage');
    expect($content)->toContain('## API Reference');
    expect($content)->toContain('## Related Packages');

    // Three key services documented
    expect($content)->toContain('CategoryTreeMarketResolver');
    expect($content)->toContain('CategoryTreeMarketAssignmentService');
    expect($content)->toContain('CategoryTreeServiceDeletePlugin');

    // Cross-links to related packages
    expect($content)->toContain('markommerce/catalog');
    expect($content)->toContain('markommerce/market');
});

it('the updated catalog.md no longer mentions resolveTreeForMarket, assignTreeToMarket, or unassignMarket', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    expect($content)->not->toContain('resolveTreeForMarket');
    expect($content)->not->toContain('assignTreeToMarket');
    expect($content)->not->toContain('unassignMarket');
});

it('the updated catalog.md cross-links to the catalog-market-category-trees page', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    expect($content)->toContain('catalog-market-category-trees');
    expect($content)->toContain('/docs/packages/catalog-market-category-trees/');
});

it('tests/Unit/Docs/CatalogMarketExtractPagesTest.php exists and all its assertions pass', function (): void {
    $file = __DIR__ . '/CatalogMarketExtractPagesTest.php';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Test file must assert new pages exist
    expect($content)->toContain('market.md');
    expect($content)->toContain('catalog-market.md');
    expect($content)->toContain('catalog-market-category-trees.md');

    // Test file must assert catalog.md no longer mentions removed APIs
    expect($content)->toContain('resolveTreeForMarket');
    expect($content)->toContain('assignTreeToMarket');
    expect($content)->toContain('unassignMarket');

    // Test file must assert catalog-market-category-trees.md mentions the three services
    expect($content)->toContain('CategoryTreeMarketResolver');
    expect($content)->toContain('CategoryTreeMarketAssignmentService');
    expect($content)->toContain('CategoryTreeServiceDeletePlugin');

    // Test file must assert catalog-market.md documents placeholder status
    expect($content)->toContain('placeholder');
});
