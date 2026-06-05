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

function presentationBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-presentation-test-' . bin2hex(random_bytes(8));
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

function presentationMakeCategory(string $name = 'Test Category'): Category
{
    $category = new Category();
    $category->id = 1;
    $category->name = $name;

    return $category;
}

/**
 * @param list<string> $pageLinkUrls
 */
function presentationRenderGrid(
    PaginationPresentation $presentation,
    array $pageLinkUrls = ['?page=1', '?page=2', '?page=3'],
    int $currentPage = 1,
    int $totalPages = 3,
    bool $hasNext = true,
    bool $hasPrevious = false,
    ?string $nextPageUrl = '?page=2',
    string $categoryName = 'Test Category',
): string {
    $engine = presentationBuildLatte();
    $category = presentationMakeCategory($categoryName);

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
    ]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('renders numbered pagination controls when presentation is numbered', function (): void {
    $output = presentationRenderGrid(
        presentation: PaginationPresentation::Numbered,
        pageLinkUrls: ['?page=1', '?page=2', '?page=3'],
        currentPage: 1,
        totalPages: 3,
        hasNext: true,
        hasPrevious: false,
        nextPageUrl: '?page=2',
    );

    // The numbered pagination partial renders a nav with catalog-pagination class
    expect($output)->toContain('catalog-pagination');
    // Should NOT render load-more or infinite-scroll web components
    expect($output)->not->toContain('mk-load-more');
    expect($output)->not->toContain('mk-infinite-scroll');
});

it('renders a load-more component when presentation is load_more', function (): void {
    $output = presentationRenderGrid(
        presentation: PaginationPresentation::LoadMore,
        pageLinkUrls: ['?page=1', '?page=2', '?page=3'],
        currentPage: 1,
        totalPages: 3,
        hasNext: true,
        hasPrevious: false,
        nextPageUrl: '?page=2',
    );

    // Should render an mk-load-more element
    expect($output)->toContain('mk-load-more');
    // Should NOT render infinite-scroll
    expect($output)->not->toContain('mk-infinite-scroll');
});

it('renders an infinite-scroll component when presentation is infinite', function (): void {
    $output = presentationRenderGrid(
        presentation: PaginationPresentation::Infinite,
        pageLinkUrls: ['?page=1', '?page=2', '?page=3'],
        currentPage: 1,
        totalPages: 3,
        hasNext: true,
        hasPrevious: false,
        nextPageUrl: '?page=2',
    );

    // Should render an mk-infinite-scroll element
    expect($output)->toContain('mk-infinite-scroll');
    // Should NOT render load-more
    expect($output)->not->toContain('mk-load-more');
});

it('always renders crawlable page links regardless of presentation mode', function (): void {
    $pageLinkUrls = ['?page=1', '?page=2', '?page=3'];

    foreach ([PaginationPresentation::Numbered, PaginationPresentation::LoadMore, PaginationPresentation::Infinite] as $mode) {
        $output = presentationRenderGrid(
            presentation: $mode,
            pageLinkUrls: $pageLinkUrls,
            currentPage: 2,
            totalPages: 3,
            hasNext: true,
            hasPrevious: true,
            nextPageUrl: '?page=3',
        );

        // All modes must contain crawlable anchor links with ?page=N
        expect($output)->toContain('href="?page=1"', 'href="?page=2"', 'href="?page=3"');
    }
});

it('exposes the next-page url to the load-more and infinite components', function (): void {
    $nextUrl = '?page=4';

    $loadMoreOutput = presentationRenderGrid(
        presentation: PaginationPresentation::LoadMore,
        pageLinkUrls: ['?page=1', '?page=2', '?page=3', '?page=4'],
        currentPage: 3,
        totalPages: 4,
        hasNext: true,
        hasPrevious: true,
        nextPageUrl: $nextUrl,
    );

    $infiniteOutput = presentationRenderGrid(
        presentation: PaginationPresentation::Infinite,
        pageLinkUrls: ['?page=1', '?page=2', '?page=3', '?page=4'],
        currentPage: 3,
        totalPages: 4,
        hasNext: true,
        hasPrevious: true,
        nextPageUrl: $nextUrl,
    );

    // Both load-more and infinite-scroll components should expose data-next
    expect($loadMoreOutput)->toContain('data-next="' . $nextUrl . '"');
    expect($infiniteOutput)->toContain('data-next="' . $nextUrl . '"');
});

it('changes the rendered controls when the presentation config changes', function (): void {
    $numberedOutput = presentationRenderGrid(presentation: PaginationPresentation::Numbered);
    $loadMoreOutput = presentationRenderGrid(presentation: PaginationPresentation::LoadMore);
    $infiniteOutput = presentationRenderGrid(presentation: PaginationPresentation::Infinite);

    // Each mode renders different primary controls
    expect($numberedOutput)->toContain('catalog-pagination');
    expect($loadMoreOutput)->toContain('mk-load-more');
    expect($infiniteOutput)->toContain('mk-infinite-scroll');

    // The numbered output should not have the web components
    expect($numberedOutput)->not->toContain('mk-load-more');
    expect($numberedOutput)->not->toContain('mk-infinite-scroll');
});
