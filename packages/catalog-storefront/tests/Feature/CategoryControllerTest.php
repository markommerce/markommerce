<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Harness helpers ──────────────────────────────────────────────────────────

function categoryControllerVendorDir(): string
{
    // __DIR__ = packages/catalog-storefront/tests/Feature
    // dirname 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function categoryControllerEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function categoryControllerMakeTestCase(): IntegrationTestCase
{
    categoryControllerEnsureConfigKey();

    return new IntegrationTestCase(
        StoreProfile::storefront(categoryControllerVendorDir()),
    );
}

// ─── Integration tests (real DB + handle()) ───────────────────────────────────

it(
    'migrates CategoryControllerTest to handle() with real factory-seeded data',
    function (): void {
        IntegrationTestCase::skipIfUnavailable();

        $testCase = categoryControllerMakeTestCase();
        $testCase->setUpIntegration();

        try {
            $store = $testCase->store;

            $category = CategoryFactory::new($store)->withName('Migration Test Category')->create();

            $request = new Request([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/catalog/category/' . $category->id,
                'HTTP_HOST' => 'localhost',
            ]);
            $response = $store->handle($request);

            expect($response->statusCode())->toBe(200);
            expect($response->body())->not->toContain('data-template=');

            $notFoundRequest = new Request([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/catalog/category/99999',
                'HTTP_HOST' => 'localhost',
            ]);
            $notFoundResponse = $store->handle($notFoundRequest);
            expect($notFoundResponse->statusCode())->toBe(404);
        } finally {
            $testCase->tearDownIntegration();
        }
    },
)->group('integration-destructive');
