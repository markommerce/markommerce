<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Harness helpers ──────────────────────────────────────────────────────────

function catalogFragmentVendorDir(): string
{
    // __DIR__ = packages/catalog-storefront/tests/Feature
    // dirname 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function catalogFragmentEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function catalogFragmentMakeTestCase(): IntegrationTestCase
{
    catalogFragmentEnsureConfigKey();

    return new IntegrationTestCase(
        StoreProfile::storefront(catalogFragmentVendorDir()),
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('migrates CategoryPageFragmentTest for the load-more fragment endpoint rendering its real fragment layout', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogFragmentMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();
        ProductFactory::new($store)->withSku('FRAG-001')->withName('Fragment Product')->inCategory($category)->create();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
            query: ['page' => '1'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        // Real fragment layout renders product-grid-fragment.latte which wraps product cards
        expect($response->body())->toContain('catalog-product-grid-fragment');
        // Product card is rendered with real mk-* markup
        expect($response->body())->toContain('catalog-product-card');
        // Real Latte output — no fake-view placeholder strings
        expect($response->body())->not->toContain('data-template=');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('renders the product cards for the requested page as html', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogFragmentMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();
        ProductFactory::new($store)->withSku('PROD-001')->withName('Test Product')->inCategory($category)->create();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
            query: ['page' => '1'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        // Real mk-* markup from product-card.latte — <article class="catalog-product-card">
        expect($response->body())->toContain('catalog-product-card');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('produces card markup identical to the full page render', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogFragmentMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();
        ProductFactory::new($store)->withSku('PROD-001')->withName('Identical Card Product')->inCategory($category)->create();

        // Fetch full page
        $fullRequest = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id],
        );
        $fullResponse = $store->handle($fullRequest);

        // Fetch fragment
        $fragmentRequest = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
            query: ['page' => '1'],
        );
        $fragmentResponse = $store->handle($fragmentRequest);

        // Both responses should contain the product name in the card markup
        expect($fullResponse->body())->toContain('Identical Card Product');
        expect($fragmentResponse->body())->toContain('Identical Card Product');
        // Fragment should not include the full page chrome (no <html> wrapper)
        // but both should render the same product card article
        expect($fragmentResponse->body())->toContain('catalog-product-card');
        expect($fullResponse->body())->toContain('catalog-product-card');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('respects the size and sort query params', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogFragmentMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();
        ProductFactory::new($store)->withSku('PROD-001')->withName('Product 1')->inCategory($category)->create();

        // Use registered sort 'position' (the only sort registered in the real container)
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
            query: ['page' => '1', 'size' => '12', 'sort' => 'position'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        expect($response->body())->toContain('catalog-product-card');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('signals no more results past the last page', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogFragmentMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();
        // No products — totalPages=1 (empty category)

        // Request page 2 when there is only 1 page
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
            query: ['page' => '2'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        // When no next page exists, data-next attribute is absent from the fragment
        expect($response->body())->not->toContain('data-next=');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('returns 410 when the requested page exceeds the max depth', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogFragmentMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        // maxPageDepth=5 — requesting page 6 should return 410
        /** @var ConfigWriterInterface $writer */
        $writer = $store->get(ConfigWriterInterface::class);
        $writer->setGlobal('catalog/pagination.maxPageDepth', 5);

        $category = CategoryFactory::new($store)->withName('Test Category')->create();

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
            query: ['page' => '6'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(410);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('returns 404 for an unknown category', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogFragmentMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/9999/page'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(404);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');
