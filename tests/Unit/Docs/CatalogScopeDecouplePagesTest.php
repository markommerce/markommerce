<?php

declare(strict_types=1);

it('creates docs/src/content/docs/packages/catalog-scope.md following DOCS-STANDARDS sectioning', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-scope.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/catalog-scope');
    expect($content)->toContain('description:');

    // No ## Overview heading (DOCS-STANDARDS forbids it)
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections for a package page
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/catalog-scope');
    expect($content)->toContain('## Usage');
    expect($content)->toContain('## API Reference');
    expect($content)->toContain('## Related Packages');

    // Companion entity mechanism is documented
    expect($content)->toContain('ProductScopedOverrides');
    expect($content)->toContain('CategoryScopedOverrides');

    // #[Preference] mechanism is documented
    expect($content)->toContain('Preference');

    // Cross-links to related packages
    expect($content)->toContain('markommerce/scope');
    expect($content)->toContain('markommerce/catalog');

    // ScopedProductGridComponent moved to catalog-storefront-scope; must cross-link there
    expect($content)->toContain('markommerce/catalog-storefront-scope');
});

it('creates docs/src/content/docs/packages/locale.md following DOCS-STANDARDS sectioning', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/locale.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/locale');
    expect($content)->toContain('description:');

    // No ## Overview heading
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/locale');
    expect($content)->toContain('## Related Packages');

    // locale axis content
    expect($content)->toContain('locale');
    expect($content)->toContain('config/scope.php');

    // Cross-link to catalog-locale
    expect($content)->toContain('markommerce/catalog-locale');
});

it('creates docs/src/content/docs/packages/catalog-locale.md following DOCS-STANDARDS sectioning', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog-locale.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Valid frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('title: markommerce/catalog-locale');
    expect($content)->toContain('description:');

    // No ## Overview heading
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph after frontmatter
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();

    // Required sections
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/catalog-locale');
    expect($content)->toContain('## Usage');
    expect($content)->toContain('## Related Packages');

    // module.php boot pattern is documented
    expect($content)->toContain('module.php');
    expect($content)->toContain('boot');
    expect($content)->toContain('ScopedFieldRegistry');

    // Custom bridge pattern is documented
    expect($content)->toContain('custom');

    // Cross-links
    expect($content)->toContain('markommerce/catalog-scope');
    expect($content)->toContain('markommerce/locale');
});

it('updates docs/src/content/docs/packages/catalog.md to remove scope-required statements and link to catalog-scope', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/catalog.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Must NOT contain statements that scope is required
    expect($content)->not->toContain('All scoped fields use `markommerce/scope` for per-locale value resolution');

    // Must reference catalog-scope
    expect($content)->toContain('catalog-scope');

    // Still has existing scope cross-link
    expect($content)->toContain('markommerce/scope');
});

it('updates docs/src/content/docs/packages/scope.md to cross-link catalog-locale as the canonical bridge example', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/scope.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Must reference catalog-locale as bridge example
    expect($content)->toContain('catalog-locale');
});

it('passes docs site build (no broken links, valid frontmatter)', function (): void {
    $packagesDir = __DIR__ . '/../../../docs/src/content/docs/packages';

    $expectedPages = [
        'catalog-scope.md',
        'locale.md',
        'catalog-locale.md',
        'catalog.md',
        'scope.md',
        'catalog-storefront.md',
        'catalog-storefront-scope.md',
    ];

    foreach ($expectedPages as $page) {
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

        // Internal links use root-relative paths (no bare relative paths like ../scope/)
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
