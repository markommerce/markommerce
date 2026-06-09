<?php

declare(strict_types=1);

/**
 * ProductGridTemplateTest — split between direct-engine renders and handle() renders.
 *
 * MIGRATION DECISION (Task 020):
 *
 * This file already renders REAL Latte HTML via the engine directly. No fake
 * ViewInterface or fake repositories are used.
 *
 * Direct-engine renders (synthetic data) — KEPT:
 *   All existing tests exercise markup BRANCHES that are only reachable by passing
 *   synthetic view-data to the template (pagination permutations: hasPrevious=true/false,
 *   pageLinkUrls=[...], presentation=LoadMore/Numbered, previousPageUrl/nextPageUrl).
 *   A single DB-seeded category+product request always produces a first-page result with
 *   no previous page, so these branches cannot be driven through handle(). They remain
 *   as focused direct-engine template renders — correct and intentional.
 *
 * handle() render — ADDED:
 *   The requirement test below exercises the "grid markup from real data" path: it seeds
 *   a category with a product via factories and dispatches a request, asserting that the
 *   real product-grid template emits <mk-grid> and the product name via the product-card
 *   template. This covers the handle() side of the split.
 */

use Latte\Engine;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Routing\Http\Request;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\Latte\ModuleLoader;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Harness helpers (for the handle() requirement test) ──────────────────────

function productGridTemplateVendorDir(): string
{
    // __DIR__ = packages/catalog-storefront/tests/Feature
    // dirname 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function productGridTemplateEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function productGridTemplateMakeTestCase(): IntegrationTestCase
{
    productGridTemplateEnsureConfigKey();

    return new IntegrationTestCase(
        StoreProfile::storefront(productGridTemplateVendorDir()),
    );
}

// ─── Direct-engine helpers (synthetic-data branches) ──────────────────────────

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

// ─── Direct-engine tests (synthetic-data branches — KEPT, not migrated to handle()) ──

// These tests exercise pagination markup branches that require synthetic view-data
// (hasPrevious=true/false, pageLinkUrls permutations). A single real request always
// produces page 1 with no previous page, so these branches are only reachable here.

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

// ─── handle() test (real data — migrated path) ────────────────────────────────

it(
    'migrates ProductGridTemplateTest asserting the grid markup from real data (or keeps direct-engine renders for synthetic-data branches, documented)',
    function (): void {
        // This test covers the handle() side of the split (see file-level doc comment).
        // Real factory-seeded data → full request dispatch → real Latte output.
        // All other tests in this file stay as direct-engine renders because their
        // markup branches (hasPrevious, pageLinkUrls permutations) require synthetic
        // view-data not producible from a single page-1 DB-seeded request.
        IntegrationTestCase::skipIfUnavailable();

        $testCase = productGridTemplateMakeTestCase();
        $testCase->setUpIntegration();

        try {
            $store = $testCase->store;

            $category = CategoryFactory::new($store)->withName('Grid Real Category')->create();
            ProductFactory::new($store)->withName('Grid Real Product')->inCategory($category)->create();

            $request = new Request([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/catalog/category/' . $category->id,
                'HTTP_HOST' => 'localhost',
            ]);
            $response = $store->handle($request);

            expect($response->statusCode())->toBe(200);
            // Real product-grid.latte renders <mk-grid> when products exist
            expect($response->body())->toContain('<mk-grid');
            // Product name appears via product-card.latte {$resolvedName}
            expect($response->body())->toContain('Grid Real Product');
            // Category name rendered in the heading
            expect($response->body())->toContain('Grid Real Category');
            // Real output — no fake-view markers
            expect($response->body())->not->toContain('data-template=');
        } finally {
            $testCase->tearDownIntegration();
        }
    },
)->group('integration-destructive');
