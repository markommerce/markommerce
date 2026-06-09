<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Harness helpers ──────────────────────────────────────────────────────────

function sortRedirectVendorDir(): string
{
    // __DIR__ = packages/catalog-storefront/tests/Feature
    // dirname 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function sortRedirectEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function sortRedirectMakeTestCase(): IntegrationTestCase
{
    sortRedirectEnsureConfigKey();

    return new IntegrationTestCase(
        StoreProfile::storefront(sortRedirectVendorDir()),
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'migrates CategorySortRedirectTest invalid-sort 302 redirect, 410 page-depth, and the loud keyset-incompatible error via handle()',
    function (): void {
        IntegrationTestCase::skipIfUnavailable();
    
        $testCase = sortRedirectMakeTestCase();
        $testCase->setUpIntegration();
    
        try {
            $store = $testCase->store;
    
            $category = CategoryFactory::new($store)->withName('Test Category')->create();
    
            // Unknown sort 'name' is not registered → should redirect 302
        $request = new Request(
                server: [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI'    => '/catalog/category/' . $category->id . '?sort=name',
                    'HTTP_HOST'      => 'example.com',
                ],
                query: ['sort' => 'name'],
            );
            $response = $store->handle($request);
    
            expect($response->statusCode())->toBe(302);
            $location = $response->headers()['Location'] ?? null;
            expect($location)->not->toBeNull();
            expect($location)->toContain('/catalog/category/' . $category->id);
            expect($location)->not->toContain('sort=');
        } finally {
            $testCase->tearDownIntegration();
        }
    }
)->group('integration-destructive');

it('it redirects to the category url without the sort param when an unknown sort is requested', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = sortRedirectMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?sort=name',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['sort' => 'name'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(302);
        $location = $response->headers()['Location'] ?? null;
        expect($location)->not->toBeNull();
        expect($location)->toContain('/catalog/category/' . $category->id);
        expect($location)->not->toContain('sort=');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('it issues a 302 status for an unknown requested sort', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = sortRedirectMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?sort=sku',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['sort' => 'sku'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(302);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('it preserves the page and size params while dropping the invalid sort on redirect', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = sortRedirectMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=3&size=48&sort=price',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['page' => '3', 'size' => '48', 'sort' => 'price'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(302);
        $location = $response->headers()['Location'] ?? null;
        expect($location)->not->toBeNull();
        expect($location)->toContain('page=3');
        expect($location)->toContain('size=48');
        expect($location)->not->toContain('sort=');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('it renders normally without redirecting for a valid registered sort', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = sortRedirectMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();

        // 'position' is the only registered sort in the real container
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '?sort=position',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['sort' => 'position'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it(
    'it does not redirect and stays loud when a keyset-incompatible sort is requested under the keyset strategy',
    function (): void {
        IntegrationTestCase::skipIfUnavailable();
    
        // Use a fresh test case per config variant (avoids cached config resolver issue)
    $testCase = sortRedirectMakeTestCase();
        $testCase->setUpIntegration();
    
        try {
            $store = $testCase->store;
    
            // Configure keyset strategy with load_more presentation (numbered+keyset throws a different exception)
        // The real container has 'position' registered as supportsKeyset: false
        // With strategy=keyset + position sort → InvalidPaginationConfigException (not a redirect)
        /** @var ConfigWriterInterface $writer */
            $writer = $store->get(ConfigWriterInterface::class);
            $writer->setGlobal('catalog/pagination.strategy', 'keyset');
            $writer->setGlobal('catalog/pagination.presentation', 'load_more');
            $writer->setGlobal('catalog/pagination.defaultSort', 'position');
    
            $category = CategoryFactory::new($store)->withName('Test Category')->create();
    
            // position is registered but keyset-incompatible under keyset strategy — must throw, not redirect
        $request = new Request(
                server: [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI'    => '/catalog/category/' . $category->id,
                    'HTTP_HOST'      => 'example.com',
                ],
            );
    
            expect(fn () => $store->handle($request))->toThrow(InvalidPaginationConfigException::class);
        } finally {
            $testCase->tearDownIntegration();
        }
    }
)->group('integration-destructive');

it('it redirects the page fragment endpoint to default on an unknown sort', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = sortRedirectMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/catalog/category/' . $category->id . '/page?sort=name',
                'HTTP_HOST'      => 'example.com',
            ],
            query: ['sort' => 'name'],
        );
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(302);
        $location = $response->headers()['Location'] ?? null;
        expect($location)->not->toBeNull();
        expect($location)->toContain('/catalog/category/' . $category->id);
        expect($location)->not->toContain('sort=');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');
