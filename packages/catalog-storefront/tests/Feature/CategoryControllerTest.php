<?php

declare(strict_types=1);

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\CatalogStorefront\Controller\CategoryController;
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

// ─── Pure-reflection tests (no DB needed) ─────────────────────────────────────

it('places a Get route at /catalog/category/{id} on the controller action', function (): void {
    $reflection = new ReflectionClass(CategoryController::class);

    $classGetAttributes = $reflection->getAttributes(Get::class);
    expect($classGetAttributes)->toBeEmpty();

    $method = $reflection->getMethod('show');
    $methodGetAttributes = $method->getAttributes(Get::class);
    expect($methodGetAttributes)->not->toBeEmpty();

    $getAttr = $methodGetAttributes[0]->newInstance();
    expect($getAttr->path)->toBe('/catalog/category/{id}');
});

it(
    'defines the layout for CategoryController show via a layout file instead of a controller attribute',
    function (): void {
        $reflection = new ReflectionClass(CategoryController::class);

        $markoLayoutClass = 'Marko\Layout\Attributes\Layout';
        $attributes = $reflection->getAttributes($markoLayoutClass);
        expect($attributes)->toBeEmpty();

        $layoutPath = dirname(__DIR__, 2) . '/layout/category_show.php';
        expect(file_exists($layoutPath))->toBeTrue();
    },
);

it('removes the standalone resources/views/category.latte template', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/category.latte';

    expect(file_exists($templatePath))->toBeFalse();
});

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

it('returns a 200 response with the assembled layout HTML when the category exists', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryControllerMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Test Category')->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(200);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('returns a 404 response when the requested category id does not exist', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryControllerMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/9999',
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $store->handle($request);

        expect($response->statusCode())->toBe(404);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('includes the category name in the rendered page heading', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryControllerMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Featured Electronics')->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $store->handle($request);

        // The product-grid.latte template renders <mk-heading size="2xl"><h1>{$category->name}</h1></mk-heading>
        expect($response->body())->toContain('Featured Electronics');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('renders every assigned product as a product grid item in the response body', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryControllerMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Shoes')->create();

        ProductFactory::new($store)->withName('Running Shoes')->withSku('SHOE-001')->inCategory($category)->create();
        ProductFactory::new($store)->withName('Hiking Boots')->withSku('SHOE-002')->inCategory($category)->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $store->handle($request);

        // Product names appear in product-card.latte via {$resolvedName}
        expect($response->body())
            ->toContain('Running Shoes')
            ->toContain('Hiking Boots');
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');

it('renders an empty-state message when the category has no products', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryControllerMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $store = $testCase->store;

        $category = CategoryFactory::new($store)->withName('Empty Category')->create();

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $store->handle($request);

        // product-grid.latte renders <mk-text variant="muted">No products found in this category.</mk-text>
        // when count($products) === 0
        expect($response->body())->toContain('No products found');
        expect($response->statusCode())->toBe(200);
    } finally {
        $testCase->tearDownIntegration();
    }
})->group('integration-destructive');
