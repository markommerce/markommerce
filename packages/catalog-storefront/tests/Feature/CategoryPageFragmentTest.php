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

it(
    'migrates CategoryPageFragmentTest for the load-more fragment endpoint rendering its real fragment layout',
    function (): void {
        IntegrationTestCase::skipIfUnavailable();

        $testCase = catalogFragmentMakeTestCase();
        $testCase->setUpIntegration();

        try {
            $store = $testCase->store;

            $category = CategoryFactory::new($store)->withName('Test Category')->create();
            ProductFactory::new($store)->withSku('FRAG-001')->withName('Fragment Product')->inCategory(
                $category,
            )->create();

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
    },
)->group('integration-destructive');

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
