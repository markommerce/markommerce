<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Harness helpers ──────────────────────────────────────────────────────────

function catalogSeoVendorDir(): string
{
    // __DIR__ = packages/catalog-storefront/tests/Feature
    // dirname 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function catalogSeoEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function catalogSeoMakeTestCase(): IntegrationTestCase
{
    catalogSeoEnsureConfigKey();

    return new IntegrationTestCase(
        StoreProfile::storefront(catalogSeoVendorDir()),
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('migrates CategorySeoTest asserting canonical and SEO headers from real data (config-driven view-all set via the real config pipeline)', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogSeoMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Electronics')->create();

        // defaultPageSize=24, defaultSort=position — requesting defaults should omit them from canonical
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=1&size=24&sort=position',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['page' => '1', 'size' => '24', 'sort' => 'position'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        $canonical = $response->headers()['Link'] ?? null;
        expect($canonical)->not->toBeNull();
        // page=1 is default — canonical should NOT include page param
        expect($canonical)->not->toContain('page=1');
        // size=24 is the default page size — should NOT be included in canonical
        expect($canonical)->not->toContain('size=24');
        // sort=position is the default — should NOT be included in canonical
        expect($canonical)->not->toContain('sort=position');
        // Canonical URL should be just the bare category path
        expect($canonical)->toContain('/catalog/category/' . $category->id . '>');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('renders all products on one page when under the view-all threshold', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogSeoMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        // Set viewAllThreshold=5 so categories with <= 5 products support view=all
        // Set defaultPageSize=2 so 3 products span multiple pages without view=all
        /** @var ConfigWriterInterface $writer */
        $writer = $store->get(ConfigWriterInterface::class);
        $writer->setGlobal('catalog/pagination.viewAllThreshold', 5);
        $writer->setGlobal('catalog/pagination.defaultPageSize', 2);

        $category = CategoryFactory::new($store)->withName('Small Category')->create();

        for ($i = 1; $i <= 3; $i++) {
            ProductFactory::new($store)->withSku('SKU-' . $i)->withName('Product ' . $i)->inCategory($category)->create();
        }

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?view=all',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['view' => 'all'],
        );
        $response = $store->handle($request);

        // View-all should return a 200 with all products rendered on one page
        expect($response->statusCode())->toBe(200);
        // The canonical should point to the view=all URL
        $canonical = $response->headers()['Link'] ?? null;
        expect($canonical)->not->toBeNull();
        expect($canonical)->toContain('view=all');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('canonicalizes paginated pages to the view-all url when view-all is active', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogSeoMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        // viewAllThreshold=5, defaultPageSize=2: a category with 3 products qualifies for view=all
        // A paginated request (page=2) should get a canonical pointing to view=all
        /** @var ConfigWriterInterface $writer */
        $writer = $store->get(ConfigWriterInterface::class);
        $writer->setGlobal('catalog/pagination.viewAllThreshold', 5);
        $writer->setGlobal('catalog/pagination.defaultPageSize', 2);

        $category = CategoryFactory::new($store)->withName('Small Category')->create();

        for ($i = 1; $i <= 3; $i++) {
            ProductFactory::new($store)->withSku('VIEW-ALL-' . $i)->inCategory($category)->create();
        }

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=2',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['page' => '2'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        $canonical = $response->headers()['Link'] ?? null;
        expect($canonical)->not->toBeNull();
        // Paginated page should canonicalize to view=all when threshold allows
        expect($canonical)->toContain('view=all');
        expect($canonical)->not->toContain('page=2');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('ignores the view-all param when the threshold is disabled', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogSeoMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        // viewAllThreshold=0 means disabled (already the default, but be explicit)
        /** @var ConfigWriterInterface $writer */
        $writer = $store->get(ConfigWriterInterface::class);
        $writer->setGlobal('catalog/pagination.viewAllThreshold', 0);

        $category = CategoryFactory::new($store)->withName('Category')->create();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?view=all',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['view' => 'all'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        $canonical = $response->headers()['Link'] ?? null;
        expect($canonical)->not->toBeNull();
        // view=all should be ignored — canonical should NOT contain view=all
        expect($canonical)->not->toContain('view=all');
        // Canonical should be the bare category URL (no params since page=1 is default)
        expect($canonical)->toContain('/catalog/category/' . $category->id);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('returns 410 gone when the requested page exceeds the max depth', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogSeoMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        // maxPageDepth=5 — requesting page 6 should return 410
        /** @var ConfigWriterInterface $writer */
        $writer = $store->get(ConfigWriterInterface::class);
        $writer->setGlobal('catalog/pagination.maxPageDepth', 5);

        $category = CategoryFactory::new($store)->withName('Deep Paginated Category')->create();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=6',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['page' => '6'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(410);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('sets a self-referencing canonical pointing at the current page', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = catalogSeoMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Electronics')->create();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=2',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['page' => '2'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
        $canonical = $response->headers()['Link'] ?? null;
        expect($canonical)->not->toBeNull();
        expect($canonical)->toContain('rel="canonical"');
        expect($canonical)->toContain('page=2');
        expect($canonical)->toContain('/catalog/category/' . $category->id);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');
