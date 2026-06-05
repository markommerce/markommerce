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

// ─── Helpers ──────────────────────────────────────────────────────────────────

function paginationBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-pagination-test-' . bin2hex(random_bytes(8));
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

/**
 * @param list<string> $pageLinkUrls
 */
function paginationRender(
    array $pageLinkUrls,
    int $currentPage,
    int $totalPages,
    bool $hasPrevious,
    bool $hasNext,
    ?string $nextPageUrl,
): string {
    $engine = paginationBuildLatte();

    return $engine->renderToString('catalog-storefront::components/product-pagination', [
        'pageLinkUrls' => $pageLinkUrls,
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'hasPrevious' => $hasPrevious,
        'hasNext' => $hasNext,
        'nextPageUrl' => $nextPageUrl,
    ]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('renders an anchor for each page in range', function (): void {
    $pageLinkUrls = [
        '?page=1',
        '?page=2',
        '?page=3',
    ];

    $output = paginationRender(
        pageLinkUrls: $pageLinkUrls,
        currentPage: 2,
        totalPages: 3,
        hasPrevious: true,
        hasNext: true,
        nextPageUrl: '?page=3',
    );

    expect($output)->toContain('href="?page=1"')
        ->and($output)->toContain('href="?page=2"')
        ->and($output)->toContain('href="?page=3"');
});

it('marks the current page as current', function (): void {
    $pageLinkUrls = [
        '?page=1',
        '?page=2',
        '?page=3',
    ];

    $output = paginationRender(
        pageLinkUrls: $pageLinkUrls,
        currentPage: 2,
        totalPages: 3,
        hasPrevious: true,
        hasNext: true,
        nextPageUrl: '?page=3',
    );

    expect($output)->toContain('aria-current="page"');
    // Only one page should be marked as current
    expect(substr_count($output, 'aria-current="page"'))->toBe(1);
});

it('preserves size and sort query params in every page link', function (): void {
    $pageLinkUrls = [
        '?page=1&size=12&sort=name',
        '?page=2&size=12&sort=name',
        '?page=3&size=12&sort=name',
    ];

    $output = paginationRender(
        pageLinkUrls: $pageLinkUrls,
        currentPage: 1,
        totalPages: 3,
        hasPrevious: false,
        hasNext: true,
        nextPageUrl: '?page=2&size=12&sort=name',
    );

    // Latte HTML-escapes & to &amp; in attributes — this is correct HTML
    expect($output)->toContain('href="?page=1&amp;size=12&amp;sort=name"')
        ->and($output)->toContain('href="?page=2&amp;size=12&amp;sort=name"')
        ->and($output)->toContain('href="?page=3&amp;size=12&amp;sort=name"');
});

it('omits the previous link on the first page', function (): void {
    $pageLinkUrls = [
        '?page=1',
        '?page=2',
        '?page=3',
    ];

    $output = paginationRender(
        pageLinkUrls: $pageLinkUrls,
        currentPage: 1,
        totalPages: 3,
        hasPrevious: false,
        hasNext: true,
        nextPageUrl: '?page=2',
    );

    expect($output)->not->toContain('rel="prev"');
});

it('omits the next link on the last page', function (): void {
    $pageLinkUrls = [
        '?page=1',
        '?page=2',
        '?page=3',
    ];

    $output = paginationRender(
        pageLinkUrls: $pageLinkUrls,
        currentPage: 3,
        totalPages: 3,
        hasPrevious: true,
        hasNext: false,
        nextPageUrl: null,
    );

    expect($output)->not->toContain('rel="next"');
});

it('renders a page X of Y label', function (): void {
    $pageLinkUrls = [
        '?page=1',
        '?page=2',
        '?page=5',
    ];

    $output = paginationRender(
        pageLinkUrls: $pageLinkUrls,
        currentPage: 2,
        totalPages: 5,
        hasPrevious: true,
        hasNext: true,
        nextPageUrl: '?page=3',
    );

    expect($output)->toContain('Page 2 of 5');
});

// ─── Smart truncation (windowed page numbers) ──────────────────────────────────

/**
 * @return list<string>
 */
function paginationUrls(int $totalPages): array
{
    $urls = [];
    for ($page = 1; $page <= $totalPages; $page++) {
        $urls[] = "?page={$page}";
    }

    return $urls;
}

it('truncates distant pages with an ellipsis instead of listing them all', function (): void {
    $output = paginationRender(
        pageLinkUrls: paginationUrls(42),
        currentPage: 7,
        totalPages: 42,
        hasPrevious: true,
        hasNext: true,
        nextPageUrl: '?page=8',
    );

    expect($output)->toContain('catalog-pagination__gap')
        ->and($output)->not->toContain('href="?page=20"');
});

it('always shows the first and last page alongside the current window', function (): void {
    $output = paginationRender(
        pageLinkUrls: paginationUrls(42),
        currentPage: 7,
        totalPages: 42,
        hasPrevious: true,
        hasNext: true,
        nextPageUrl: '?page=8',
    );

    expect($output)->toContain('href="?page=1"')
        ->and($output)->toContain('href="?page=42"')
        ->and($output)->toContain('href="?page=5"')
        ->and($output)->toContain('href="?page=9"');
    expect(substr_count($output, 'aria-current="page"'))->toBe(1);
});

it('renders a disabled previous control on the first page', function (): void {
    $output = paginationRender(
        pageLinkUrls: paginationUrls(42),
        currentPage: 1,
        totalPages: 42,
        hasPrevious: false,
        hasNext: true,
        nextPageUrl: '?page=2',
    );

    expect($output)->toContain('catalog-pagination__link--disabled')
        ->and($output)->not->toContain('rel="prev"');
});
