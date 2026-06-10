<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Harness helpers ──────────────────────────────────────────────────────────

function presentationVendorDir(): string
{
    // __DIR__ = packages/catalog-storefront/tests/Feature
    // dirname 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function presentationEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function presentationMakeTestCase(): IntegrationTestCase
{
    presentationEnsureConfigKey();

    return new IntegrationTestCase(
        StoreProfile::storefront(presentationVendorDir()),
    );
}

/**
 * Write presentation config and page size, create a category with products, dispatch a request.
 * Returns the response body.
 *
 * Each call creates its own fresh IntegrationTestCase so the config resolver's in-memory
 * cache starts empty and picks up the DB-written values on the first request.
 *
 * @param string $presentation 'numbered', 'load_more', or 'infinite'
 * @param int $productCount number of products to seed (must be >= 2 for pagination to appear with pageSize=1)
 * @return string the rendered HTML body
 */
function presentationHandleWithMode(string $presentation, int $productCount = 2, int $page = 1): string
{
    $testCase = presentationMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        /** @var ConfigWriterInterface $writer */
        $writer = $store->get(ConfigWriterInterface::class);
        // Set presentation mode BEFORE first handle() so the resolver reads it fresh from DB
        $writer->setGlobal('catalog/pagination.presentation', $presentation);
        // Set page size to 1 so $productCount products span $productCount pages
        $writer->setGlobal('catalog/pagination.defaultPageSize', 1);

        $category = CategoryFactory::new($store)->withName('Category-' . $presentation)->create();

        for ($i = 1; $i <= $productCount; $i++) {
            ProductFactory::new($store)->withSku($presentation . '-' . $i)->inCategory($category)->create();
        }

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . ($page > 1 ? '?page=' . $page : ''),
                'HTTP_HOST'      => 'localhost',
            ],
            query: $page > 1 ? ['page' => (string) $page] : [],
        );

        return $store->handle($request)->body();
    } finally {
        $testCase->tearDownIntegration();
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('migrates PresentationSwitchTest across presentation modes driven by real config writes', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    // Verify all three modes via separate fresh stores (one config write per handle() call)
    $numberedBody = presentationHandleWithMode('numbered');
    expect($numberedBody)->toContain('catalog-pagination');
    expect($numberedBody)->not->toContain('mk-load-more');
    expect($numberedBody)->not->toContain('mk-infinite-scroll');

    $loadMoreBody = presentationHandleWithMode('load_more');
    expect($loadMoreBody)->toContain('mk-load-more');
    expect($loadMoreBody)->not->toContain('mk-infinite-scroll');

    $infiniteBody = presentationHandleWithMode('infinite');
    expect($infiniteBody)->toContain('mk-infinite-scroll');
    expect($infiniteBody)->not->toContain('mk-load-more');
})->group('integration-destructive');
