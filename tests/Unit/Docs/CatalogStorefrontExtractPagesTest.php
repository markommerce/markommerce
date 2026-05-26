<?php

declare(strict_types=1);

it('creates docs/src/content/docs/packages/catalog-storefront.md following DOCS-STANDARDS sectioning', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-storefront.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/catalog-storefront');
    expect($content)->toContain('description:');

    // No ## Overview heading (DOCS-STANDARDS forbids it)
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections for a package page
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/catalog-storefront');
    expect($content)->toContain('## Usage');
    expect($content)->toContain('## API Reference');
    expect($content)->toContain('## Related Packages');

    // Storefront route documented
    expect($content)->toContain('CategoryController');
    expect($content)->toContain('/catalog/category/{id}');

    // Components documented
    expect($content)->toContain('ProductGridComponent');
    expect($content)->toContain('ProductCard');
    expect($content)->toContain('StockBadge');

    // Data DTOs documented
    expect($content)->toContain('ProductGridData');
    expect($content)->toContain('ProductCardData');

    // Cross-links to related packages
    expect($content)->toContain('markommerce/catalog');
    expect($content)->toContain('markommerce/layout');
    expect($content)->toContain('markommerce/theme-blank');
    expect($content)->toContain('markommerce/catalog-storefront-scope');
});

it('creates docs/src/content/docs/packages/catalog-storefront-scope.md following DOCS-STANDARDS sectioning', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-storefront-scope.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/catalog-storefront-scope');
    expect($content)->toContain('description:');

    // No ## Overview heading (DOCS-STANDARDS forbids it)
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections for a package page
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/catalog-storefront-scope');
    expect($content)->toContain('## Usage');
    expect($content)->toContain('## API Reference');
    expect($content)->toContain('## Related Packages');

    // ScopedProductGridComponent and Preference mechanism documented
    expect($content)->toContain('ScopedProductGridComponent');
    expect($content)->toContain('Preference');

    // Cross-links to related packages
    expect($content)->toContain('markommerce/catalog-storefront');
    expect($content)->toContain('markommerce/catalog-scope');
    expect($content)->toContain('markommerce/catalog-locale');
});

it('updates docs/src/content/docs/packages/catalog.md to remove the Storefront Route section, the Layout definition section, and the ProductGridComponent and ProductCard overviews, cross-linking to catalog-storefront instead', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Removed sections must not appear
    expect($content)->not->toContain('### Storefront route');
    expect($content)->not->toContain('### Layout definition');
    expect($content)->not->toContain('### ProductGridComponent');
    expect($content)->not->toContain('### ProductCard and ProductCardData');

    // Must cross-link to catalog-storefront
    expect($content)->toContain('markommerce/catalog-storefront');
    expect($content)->toContain('/docs/packages/catalog-storefront/');
});

it('updates docs/src/content/docs/packages/catalog-scope.md to remove the ScopedProductGridComponent section, cross-linking to catalog-storefront-scope instead', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-scope.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // ScopedProductGridComponent section must be removed
    expect($content)->not->toContain('### ScopedProductGridComponent');

    // Must cross-link to catalog-storefront-scope as the new home
    expect($content)->toContain('markommerce/catalog-storefront-scope');
    expect($content)->toContain('/docs/packages/catalog-storefront-scope/');

    // Core companion entity content must remain
    expect($content)->toContain('ProductScopedOverrides');
    expect($content)->toContain('CategoryScopedOverrides');
});

it('updates tests/Unit/Docs/CatalogScopeDecouplePagesTest.php to drop the assertion that catalog-scope.md contains the ScopedProductGridComponent literal (and the related Preference literal, if no other Preference reference remains on the page), and replace with an assertion that catalog-scope.md cross-links to markommerce/catalog-storefront-scope', function (): void {
    $file = __DIR__ . '/CatalogScopeDecouplePagesTest.php';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // The old assertion that catalog-scope.md contains ScopedProductGridComponent must be removed
    expect($content)->not->toContain("toContain('ScopedProductGridComponent')");

    // Must have replacement assertion cross-linking to catalog-storefront-scope
    expect($content)->toContain('catalog-storefront-scope');
});

it('adds catalog-storefront.md and catalog-storefront-scope.md to the expectedPages list inside CatalogScopeDecouplePagesTest.php\'s docs-build smoke test (or migrate that smoke loop into the new CatalogStorefrontExtractPagesTest)', function (): void {
    $file = __DIR__ . '/CatalogScopeDecouplePagesTest.php';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Both new pages must appear in the smoke test expected pages list
    expect($content)->toContain('catalog-storefront.md');
    expect($content)->toContain('catalog-storefront-scope.md');
});

it('passes the docs-site build with no broken links, valid frontmatter, and no ## Overview heading on any new or edited page', function (): void {
    $packagesDir = __DIR__ . '/../../../docs/src/content/docs/packages';

    $affectedPages = [
        'catalog-storefront.md',
        'catalog-storefront-scope.md',
        'catalog.md',
        'catalog-scope.md',
    ];

    foreach ($affectedPages as $page) {
        $file = $packagesDir . '/' . $page;
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);

        // Valid frontmatter block
        expect($content)->toMatch('/^---\n/');
        expect($content)->toContain('title:');
        expect($content)->toContain('description:');

        // Frontmatter closes
        expect(preg_match('/^---.*?---/s', $content))->toBe(1);

        // No ## Overview (DOCS-STANDARDS.md rule)
        expect($content)->not->toContain('## Overview');

        // Internal links use root-relative paths
        preg_match_all('/\[.*?\]\((.*?)\)/', $content, $matches);
        foreach ($matches[1] as $link) {
            if (str_starts_with($link, 'http') || str_starts_with($link, '#')) {
                continue;
            }
            // Internal doc links must be root-relative (start with /)
            expect($link)->toStartWith('/');
        }
    }
});

it('ensures every internal link on the new and edited docs pages is root-relative (starts with /)', function (): void {
    $packagesDir = __DIR__ . '/../../../docs/src/content/docs/packages';

    $newAndEditedPages = [
        'catalog-storefront.md',
        'catalog-storefront-scope.md',
        'catalog.md',
        'catalog-scope.md',
    ];

    foreach ($newAndEditedPages as $page) {
        $file = $packagesDir . '/' . $page;
        $content = file_get_contents($file);

        preg_match_all('/\[.*?\]\((.*?)\)/', $content, $matches);
        foreach ($matches[1] as $link) {
            if (str_starts_with($link, 'http') || str_starts_with($link, '#')) {
                continue;
            }
            expect($link)->toStartWith('/', "Internal link '{$link}' in {$page} must be root-relative");
        }
    }
});
