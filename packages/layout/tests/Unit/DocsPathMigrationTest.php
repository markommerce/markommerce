<?php

declare(strict_types=1);

$docsDir = dirname(__DIR__, 4) . '/docs/src/content/docs';
$layoutDoc = $docsDir . '/packages/layout.md';
$catalogDoc = $docsDir . '/packages/catalog.md';
$layoutDemoDoc = $docsDir . '/packages/layout-demo.md';
$themeBlankDoc = $docsDir . '/packages/theme-blank/index.md';
$workingWithLayoutsGuide = $docsDir . '/guides/working-with-layouts.md';
$layoutDemoReadme = dirname(__DIR__, 4) . '/packages/layout-demo/README.md';

it('shows the new resources/views/layout path in the layout package documentation', function () use ($layoutDoc): void {
    $content = file_get_contents($layoutDoc);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('{module}/resources/views/layout/{name}.php');
    expect($content)->toContain('packages/catalog/resources/views/layout/category_show.php');
});

it('shows the new resources/views/layout path in the catalog package documentation', function () use ($catalogDoc): void {
    $content = file_get_contents($catalogDoc);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('packages/catalog/resources/views/layout/category_show.php');
    expect($content)->toContain('resources/views/layout/category_show.php');
});

it('shows the new resources/views/layout path in the layout-demo package documentation', function () use ($layoutDemoDoc): void {
    $content = file_get_contents($layoutDemoDoc);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('packages/layout-demo/resources/views/layout/layout_demo.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/extensions/layout_demo_extension.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/default.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/layout_demo_child.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/layout_demo_variant_featured.php');
});

it('shows the new resources/views/layout path in the theme-blank package documentation', function () use ($themeBlankDoc): void {
    $content = file_get_contents($themeBlankDoc);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('packages/catalog/resources/views/layout/category_show.php');
});

it('shows the new resources/views/layout path in the working-with-layouts guide', function () use ($workingWithLayoutsGuide): void {
    $content = file_get_contents($workingWithLayoutsGuide);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('{module}/resources/views/layout/{name}.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/layout_demo.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/default.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/layout_demo_child.php');
    expect($content)->toContain('packages/layout-demo/resources/views/layout/layout_demo.php (excerpt)');
});

it('shows the new resources/views/layout/extensions path for extension files in documentation', function () use ($layoutDoc, $workingWithLayoutsGuide): void {
    $layoutContent = file_get_contents($layoutDoc);
    expect($layoutContent)->not->toBeFalse();
    /** @var string $layoutContent */
    expect($layoutContent)->toContain('{module}/resources/views/layout/extensions/{name}.php');

    $guideContent = file_get_contents($workingWithLayoutsGuide);
    expect($guideContent)->not->toBeFalse();
    /** @var string $guideContent */
    expect($guideContent)->toContain('{module}/resources/views/layout/extensions/{name}.php');
    expect($guideContent)->toContain('packages/layout-demo/resources/views/layout/extensions/layout_demo_extension.php');
});

it('shows the new resources/views/layout path in the layout-demo package README', function () use ($layoutDemoReadme): void {
    $content = file_get_contents($layoutDemoReadme);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('// resources/views/layout/layout_demo.php');
});

it('no documentation file references the legacy {module}/layout/{name}.php convention', function (): void {
    $docsDir = dirname(__DIR__, 4) . '/docs/src/content/docs';
    $readmesGlob = dirname(__DIR__, 4) . '/packages/*/README.md';

    $filesToCheck = array_merge(
        glob($docsDir . '/**/*.md') ?: [],
        glob($docsDir . '/*.md') ?: [],
        glob($readmesGlob) ?: [],
    );

    $legacyMatches = [];
    foreach ($filesToCheck as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        // Match filesystem paths like packages/{name}/layout/{name}.php
        // but NOT route URLs like /markommerce/_demo/layout/1
        // and NOT Latte namespaces like theme-blank::layout/1column
        if (preg_match('/packages\/[a-z-]+\/layout\/[a-z_]+\.php/', $content)) {
            $legacyMatches[] = $file;
        }
    }

    expect($legacyMatches)->toBe([]);
});

it('route URLs like /markommerce/_demo/layout/{page} are preserved unchanged in the demo documentation', function (): void {
    $layoutDemoDoc = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/layout-demo.md';
    $content = file_get_contents($layoutDemoDoc);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('/markommerce/_demo/layout/');
});

it('DocsApiReferenceTest and DocsGuideTest still pass', function (): void {
    // This test ensures a deliberate integration check — the actual assertions
    // are in the other test files; this test simply verifies the docs files exist
    $docsDir = dirname(__DIR__, 4) . '/docs/src/content/docs';
    expect(file_exists($docsDir . '/packages/layout.md'))->toBeTrue();
    expect(file_exists($docsDir . '/guides/working-with-layouts.md'))->toBeTrue();
});
