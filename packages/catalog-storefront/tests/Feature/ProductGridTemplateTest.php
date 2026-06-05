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
use Markommerce\Catalog\Pagination\PaginationPresentation;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function gridTemplateBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-grid-template-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $catalogPath = dirname(__DIR__, 2);

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

function gridTemplateRenderFragment(
    ?string $nextPageUrl = null,
    ?string $previousPageUrl = null,
    ?string $canonicalPageUrl = null,
): string {
    $engine = gridTemplateBuildLatte();

    return $engine->renderToString('catalog-storefront::components/product-grid-fragment', [
        'nextPageUrl' => $nextPageUrl,
        'previousPageUrl' => $previousPageUrl,
        'canonicalPageUrl' => $canonicalPageUrl,
    ]);
}

/**
 * @param list<string> $pageLinkUrls
 */
function gridTemplateRenderGrid(
    PaginationPresentation $presentation,
    array $pageLinkUrls = ['?page=1', '?page=2', '?page=3'],
    int $currentPage = 2,
    int $totalPages = 3,
    bool $hasNext = true,
    bool $hasPrevious = true,
    ?string $nextPageUrl = '?page=3',
    ?string $previousPageUrl = '/catalog/category/1/page?page=1',
    ?string $canonicalPageUrl = '/catalog/category/1?page=2',
): string {
    $engine = gridTemplateBuildLatte();

    $category = new Category();
    $category->id = 1;
    $category->name = 'Test Category';

    return $engine->renderToString('catalog-storefront::components/product-grid', [
        'category' => $category,
        'products' => [],
        'resolvedNames' => [],
        'resolvedDescs' => [],
        'formattedPrices' => [],
        'presentation' => $presentation,
        'pageLinkUrls' => $pageLinkUrls,
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'hasNext' => $hasNext,
        'hasPrevious' => $hasPrevious,
        'nextPageUrl' => $nextPageUrl,
        'previousPageUrl' => $previousPageUrl,
        'canonicalPageUrl' => $canonicalPageUrl,
    ]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('emits data-prev on the fragment wrapper when a previous page exists', function (): void {
    $output = gridTemplateRenderFragment(
        nextPageUrl: '?page=3',
        previousPageUrl: '/catalog/category/1/page?page=1',
        canonicalPageUrl: '/catalog/category/1?page=2',
    );

    expect($output)->toContain('data-prev="/catalog/category/1/page?page=1"');
});

it('omits data-prev on the fragment wrapper for the first page', function (): void {
    $output = gridTemplateRenderFragment(
        nextPageUrl: '?page=2',
        previousPageUrl: null,
        canonicalPageUrl: '/catalog/category/1?page=1',
    );

    expect($output)->not->toContain('data-prev');
});

it('emits data-canonical on the fragment wrapper', function (): void {
    $output = gridTemplateRenderFragment(
        nextPageUrl: '?page=3',
        previousPageUrl: '/catalog/category/1/page?page=1',
        canonicalPageUrl: '/catalog/category/1?page=2',
    );

    expect($output)->toContain('data-canonical="/catalog/category/1?page=2"');
});

it('renders a load-previous button above the grid when previousPageUrl is set', function (): void {
    $engine = gridTemplateBuildLatte();

    $category = new Category();
    $category->id = 1;
    $category->name = 'Test Category';

    // Pass a non-empty products array so mk-grid is rendered
    $product = new stdClass();

    $output = $engine->renderToString('catalog-storefront::components/product-grid', [
        'category' => $category,
        'products' => [$product],
        'resolvedNames' => [],
        'resolvedDescs' => [],
        'formattedPrices' => [],
        'presentation' => PaginationPresentation::LoadMore,
        'pageLinkUrls' => ['?page=1', '?page=2', '?page=3'],
        'currentPage' => 2,
        'totalPages' => 3,
        'hasNext' => true,
        'hasPrevious' => true,
        'nextPageUrl' => '?page=3',
        'previousPageUrl' => '/catalog/category/1/page?page=1',
        'canonicalPageUrl' => '/catalog/category/1?page=2',
    ]);

    expect($output)->toContain('data-role="load-previous"');
    expect($output)->toContain('catalog-pagination__load-previous');

    // The load-previous button must appear BEFORE the mk-grid element
    $loadPreviousPos = strpos($output, 'data-role="load-previous"');
    $mkGridPos = strpos($output, '<mk-grid');
    expect($loadPreviousPos)->not->toBeFalse();
    expect($mkGridPos)->not->toBeFalse();
    expect((int) $loadPreviousPos)->toBeLessThan((int) $mkGridPos);
});

it('does not render a load-previous button on the first page', function (): void {
    $output = gridTemplateRenderGrid(
        presentation: PaginationPresentation::LoadMore,
        previousPageUrl: null,
    );

    expect($output)->not->toContain('data-role="load-previous"');
});

it('renders a load-more button in load_more mode', function (): void {
    $output = gridTemplateRenderGrid(
        presentation: PaginationPresentation::LoadMore,
    );

    expect($output)->toContain('data-role="load-more"');
});

it('passes data-prev and data-canonical to the mk element', function (): void {
    $output = gridTemplateRenderGrid(
        presentation: PaginationPresentation::LoadMore,
        previousPageUrl: '/catalog/category/1/page?page=1',
        canonicalPageUrl: '/catalog/category/1?page=2',
    );

    expect($output)->toContain('data-prev="/catalog/category/1/page?page=1"');
    expect($output)->toContain('data-canonical="/catalog/category/1?page=2"');
});
