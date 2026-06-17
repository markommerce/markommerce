<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\Latte\ModuleLoader;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Markommerce\Catalog\Entity\Category;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function polishBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-polish-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $catalogPath = dirname(__DIR__, 3);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $catalogPath,
            source: 'vendor',
        ),
    ]);

    $config = new ConfigRepository([
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $viewConfig = new ViewConfig($config);
    $latteViewConfig = new LatteViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engine = (new LatteEngineFactory($viewConfig, $latteViewConfig))->create();
    $engine->setLoader(new ModuleLoader($templateResolver));

    return $engine;
}

function polishRenderGrid(array $extra = []): string
{
    $engine = polishBuildLatte();

    $category = new Category();
    $category->id = 1;
    $category->name = 'Test Category';

    return $engine->renderToString('catalog-storefront::components/product-grid', array_merge([
        'category' => $category,
        'products' => [],
        'resolvedNames' => [],
        'resolvedDescs' => [],
        'formattedPrices' => [],
    ], $extra));
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('styles the sort control toolbar in the components layer', function (): void {
    $cssPath = dirname(__DIR__, 3) . '/resources/css/components/category.css';

    expect(file_exists($cssPath))->toBeTrue();

    $css = file_get_contents($cssPath);

    expect($css)->toContain('@layer components');
    expect($css)->toContain('.catalog-sort-form');
    expect($css)->toContain('--mk-');
});

it('preserves the no-js sort form and its hidden filter inputs', function (): void {
    $output = polishRenderGrid([
        'sortOptions' => [
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'price_asc', 'label' => 'Price ascending'],
        ],
        'activeSort' => 'position',
        'appliedFilters' => ['color' => ['red'], 'size' => ['M']],
    ]);

    // Sort form present
    expect($output)->toContain('class="catalog-sort-form"');
    // Hidden filter inputs preserved
    expect($output)->toContain('name="filter[color][]"');
    expect($output)->toContain('name="filter[size][]"');
    // No-JS fallback button present
    expect($output)->toContain('<noscript>');
    expect($output)->toContain('type="submit"');
    // onchange submit preserved
    expect($output)->toContain('onchange="this.form.submit()"');
});

it('applies grid spacing and heading styles via mk design tokens', function (): void {
    $cssPath = dirname(__DIR__, 3) . '/resources/css/components/category.css';

    expect(file_exists($cssPath))->toBeTrue();

    $css = file_get_contents($cssPath);

    // Heading styles
    expect($css)->toContain('catalog-product-grid');
    // Uses --mk-* tokens with fallbacks
    expect($css)->toContain('var(--mk-');
});

it('styles pagination current and disabled states', function (): void {
    $cssPath = dirname(__DIR__, 3) . '/resources/css/components/pagination.css';

    expect(file_exists($cssPath))->toBeTrue();

    $css = file_get_contents($cssPath);

    expect($css)->toContain('@layer components');
    expect($css)->toContain('catalog-pagination__link--current');
    expect($css)->toContain('catalog-pagination__link--disabled');
    // Tokens with fallbacks
    expect($css)->toContain('var(--mk-color-primary');
    expect($css)->toContain('var(--mk-color-fg-muted');
});

it('renders the empty product state with a styled message', function (): void {
    $output = polishRenderGrid([
        'products' => [],
    ]);

    expect($output)->toContain('<mk-text');
    expect($output)->toContain('variant="muted"');
    expect($output)->toContain('No products found');
});
